<?php

namespace App\Imports;

use App\Imports\Concerns\CapsImportRows;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentEnrollmentService;
use App\Services\StudentIdGeneratorService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

class StudentsImport implements OnEachRow, SkipsEmptyRows, SkipsOnFailure, WithHeadingRow, WithValidation
{
    use CapsImportRows, Importable, SkipsFailures;

    /** Same two-slot shape as the admission form's guardian sub-form typically covers (father/mother). */
    private const GUARDIAN_SLOTS = [1, 2];

    /** Name-similarity floor (see similar_text()'s percent output) for flagging a possible duplicate — tuned to catch typo-distance names ("Rahul Sharma" vs "Rahull Sharma") without flagging genuinely different people who happen to share a DOB. */
    private const DUPLICATE_NAME_SIMILARITY_THRESHOLD = 82.0;

    private int $importedCount = 0;

    /** @var array<int, array{row: int, message: string}> rows that were imported/would-be-imported but closely match an existing student — never blocks the row, per the "never auto-merge uncertain people, just warn" rule (see checkForPossibleDuplicate()). */
    private array $duplicateWarnings = [];

    /** $dryRun runs grade/section resolution and every validation rule without writing anything — the preview step the controller's `dry_run` flag drives. importedCount() means "would import" in this mode. */
    public function __construct(
        private readonly School $school,
        private readonly AcademicYear $academicYear,
        private readonly User $performedBy,
        private readonly StudentIdGeneratorService $idGenerator,
        private readonly StudentEnrollmentService $enrollment,
        private readonly bool $dryRun = false,
    ) {}

    public function onRow(Row $row): void
    {
        if ($this->overRowCap($row)) {
            return;
        }

        $data = $row->toCollection();

        $gradeLevel = GradeLevel::query()->where('code', $data['grade_level_code'])->first();
        $section = Section::query()
            ->where('academic_year_id', $this->academicYear->id)
            ->where('grade_level_id', $gradeLevel?->id)
            ->where('name', $data['section_name'])
            ->first();

        if (! $gradeLevel || ! $section) {
            $this->failures[] = new Failure(
                $row->getIndex(),
                'grade_level_code',
                ['No matching grade level/section found for the given codes.'],
                $data->toArray(),
            );

            return;
        }

        $department = null;

        if (filled($data['department_code'] ?? null)) {
            $department = Department::query()->where('code', $data['department_code'])->first();

            if (! $department) {
                $this->failures[] = new Failure(
                    $row->getIndex(),
                    'department_code',
                    ['No matching department found for the given code.'],
                    $data->toArray(),
                );

                return;
            }
        }

        $this->checkForPossibleDuplicate($row->getIndex(), $data);

        if (! $this->dryRun) {
            $student = Student::query()->create([
                'admission_number' => $this->idGenerator->generate($this->school, now()),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => strtolower($data['gender']),
                'date_of_birth' => $data['date_of_birth'],
                'blood_group' => $data['blood_group'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'current_grade_level_id' => $gradeLevel->id,
                'current_section_id' => $section->id,
                'current_department_id' => $department?->id,
                'academic_year_id' => $this->academicYear->id,
                'roll_number' => $data['roll_number'] ?? null,
                'admission_date' => $data['admission_date'] ?? now()->toDateString(),
                'status' => 'active',
                'previous_school_name' => $data['previous_school_name'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'city' => $data['city'] ?? null,
            ]);

            $this->enrollment->recordAdmission($student, $this->performedBy);

            foreach (self::GUARDIAN_SLOTS as $slot) {
                $this->attachGuardianFromSlot($student, $data, $slot);
            }
        }

        $this->importedCount++;
    }

    /**
     * A `guardian{slot}_first_name` cell is what marks the slot as used —
     * an empty/missing one means this student has no guardian in this slot
     * and the rest of the slot's columns are ignored, same "at least the
     * name marks it filled" convention `McqQuestionsImport`'s option slots
     * use. Reuses an existing Guardian by email when one is given (the
     * common case for siblings imported in the same or a later file)
     * rather than always creating a duplicate row.
     */
    private function attachGuardianFromSlot(Student $student, Collection $data, int $slot): void
    {
        $firstName = $data["guardian{$slot}_first_name"] ?? null;

        if (! $firstName) {
            return;
        }

        $email = $data["guardian{$slot}_email"] ?? null;

        $attributes = [
            'first_name' => $firstName,
            'last_name' => $data["guardian{$slot}_last_name"] ?? '',
            'email' => $email,
            'phone' => $data["guardian{$slot}_phone"] ?? null,
        ];

        $guardian = $email
            ? Guardian::query()->firstOrCreate(['email' => $email], $attributes)
            : Guardian::query()->create($attributes);

        $student->guardians()->attach($guardian->id, [
            'relationship_type' => strtolower($data["guardian{$slot}_relationship"] ?? 'guardian'),
            'is_primary' => $this->parseBoolean($data["guardian{$slot}_is_primary"] ?? null, false),
            'can_pickup' => $this->parseBoolean($data["guardian{$slot}_can_pickup"] ?? null, true),
        ]);
    }

    private function parseBoolean(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'yes', 'y', 'true'], true);
    }

    /**
     * A warning, never a Failure — this row still gets imported (or would,
     * in dry-run). Per the "never automatically merge uncertain people"
     * rule: two students with the exact same date of birth and a
     * closely-matching name are flagged for a human to review afterward,
     * not silently merged and not blocked from being created (a school
     * legitimately can have twins, or two unrelated kids who share a
     * birthday and a common surname — this is a nudge to check, not a
     * validation rule). Read-only: runs identically in dry-run and real
     * commits, so a preview surfaces the same warnings the real import
     * would.
     */
    private function checkForPossibleDuplicate(int $rowIndex, Collection $data): void
    {
        $dateOfBirth = (string) $data['date_of_birth'];
        $fullName = trim($data['first_name'].' '.$data['last_name']);

        // whereDate(), not where() — a bare string-equality where() on a
        // date-cast column is driver-dependent: SQLite (tests) persists the
        // cast's full "Y-m-d H:i:s" serialization as literal text (no native
        // DATE type to coerce it), so a bare date string silently matches
        // nothing there even though the identical comparison works against
        // MySQL's real DATE column in production (reproduced directly while
        // writing this). whereDate() compares only the date part on every
        // driver.
        $candidates = Student::query()
            ->whereDate('date_of_birth', $dateOfBirth)
            ->get(['id', 'admission_number', 'first_name', 'last_name']);

        foreach ($candidates as $candidate) {
            similar_text(mb_strtolower($fullName), mb_strtolower($candidate->full_name), $percent);

            if ($percent >= self::DUPLICATE_NAME_SIMILARITY_THRESHOLD) {
                $this->duplicateWarnings[] = [
                    'row' => $rowIndex,
                    'message' => "Possible existing student: {$candidate->full_name} (admission #{$candidate->admission_number}), same date of birth. Review before treating this as a new admission.",
                ];

                return; // one warning per row is enough, even if multiple candidates match.
            }
        }
    }

    /** @return array<int, array{row: int, message: string}> */
    public function duplicateWarnings(): array
    {
        return $this->duplicateWarnings;
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female,other,Male,Female,Other'],
            'date_of_birth' => ['required', 'date'],
            'grade_level_code' => ['required', 'string'],
            'section_name' => ['required', 'string'],
            'department_code' => ['nullable', 'string'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
        ];

        foreach (self::GUARDIAN_SLOTS as $slot) {
            $rules["guardian{$slot}_first_name"] = ['nullable', 'string', 'max:100'];
            $rules["guardian{$slot}_last_name"] = ['nullable', "required_with:guardian{$slot}_first_name", 'string', 'max:100'];
            $rules["guardian{$slot}_email"] = ['nullable', 'email', 'max:255'];
            $rules["guardian{$slot}_phone"] = ['nullable', "required_with:guardian{$slot}_first_name", 'string', 'max:30'];
            $rules["guardian{$slot}_relationship"] = ['nullable', "required_with:guardian{$slot}_first_name", 'in:father,mother,guardian,other,Father,Mother,Guardian,Other'];
        }

        return $rules;
    }
}
