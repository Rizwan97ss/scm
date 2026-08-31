<?php

namespace App\Imports;

use App\Imports\Concerns\CapsImportRows;
use App\Models\Guardian;
use App\Models\Student;
use App\Rules\ValidName;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

/**
 * Deliberately NOT a SimpleLookupImport subclass — a guardian isn't a
 * standalone entity with its own unique code, it's always the result of
 * linking a person to a student (mirrors StudentsImport's guardian1_ and
 * guardian2_ slot columns, just as its own standalone import for attaching
 * guardians to students already in the system, without re-importing the
 * students). Each row resolves a target Student by admission_number,
 * reuses an existing Guardian by email if one matches (same
 * sibling-sharing behavior StudentsImport already has — a parent with two
 * kids gets one Guardian row, not two), and attaches/updates the pivot.
 *
 * Supports the same create/update/upsert modes as the lookup imports:
 * 'create' fails a row if this exact student+guardian link already
 * exists; 'update' fails if it doesn't; 'upsert' does either.
 */
class GuardiansImport implements OnEachRow, SkipsEmptyRows, SkipsOnFailure, WithHeadingRow, WithValidation
{
    use CapsImportRows, Importable, SkipsFailures;

    public const MODE_CREATE = 'create';

    public const MODE_UPDATE = 'update';

    public const MODE_UPSERT = 'upsert';

    public const MODES = [self::MODE_CREATE, self::MODE_UPDATE, self::MODE_UPSERT];

    private int $attachedCount = 0;

    private int $updatedCount = 0;

    /** @var array<string, true> normalized (student admission number + guardian email/name) pairs already seen in this file */
    private array $seenPairs = [];

    public function __construct(
        private readonly bool $dryRun = false,
        private readonly string $mode = self::MODE_CREATE,
    ) {}

    public function onRow(Row $row): void
    {
        if ($this->overRowCap($row)) {
            return;
        }

        $data = $row->toCollection();
        $admissionNumber = trim((string) $data['student_admission_number']);
        $email = trim((string) ($data['email'] ?? ''));

        $dedupeKey = mb_strtolower($admissionNumber.'|'.($email !== '' ? $email : $data['first_name'].' '.$data['last_name']));

        if (isset($this->seenPairs[$dedupeKey])) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'student_admission_number',
                ['This student/guardian pair is a duplicate of an earlier row in this file.'],
                $data->toArray(),
            );

            return;
        }

        $student = Student::query()->where('admission_number', $admissionNumber)->first();

        if (! $student) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'student_admission_number',
                ["No student with admission number \"{$admissionNumber}\" was found."],
                $data->toArray(),
            );

            return;
        }

        // Read-only lookup even in dry-run — never Guardian::firstOrCreate()
        // here, that would write to the DB during a preview.
        $existingGuardian = $email !== '' ? Guardian::query()->where('email', $email)->first() : null;
        $alreadyLinked = $existingGuardian && $student->guardians()->where('guardian_id', $existingGuardian->id)->exists();

        if ($this->mode === self::MODE_CREATE && $alreadyLinked) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'student_admission_number',
                ['This guardian is already linked to this student.'],
                $data->toArray(),
            );

            return;
        }

        if ($this->mode === self::MODE_UPDATE && ! $alreadyLinked) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'student_admission_number',
                ['This guardian is not currently linked to this student — nothing to update.'],
                $data->toArray(),
            );

            return;
        }

        $this->seenPairs[$dedupeKey] = true;

        $pivotAttributes = [
            'relationship_type' => mb_strtolower(trim((string) $data['relationship_type'])),
            'is_primary' => $this->parseBoolean($data['is_primary'] ?? null, false),
            'can_pickup' => $this->parseBoolean($data['can_pickup'] ?? null, true),
        ];

        if (! $this->dryRun) {
            $attributes = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email ?: null,
                'phone' => $data['phone'] ?? null,
            ];

            $guardian = $email !== ''
                ? Guardian::query()->firstOrCreate(['email' => $email], $attributes)
                : Guardian::query()->create($attributes);

            if ($alreadyLinked) {
                $student->guardians()->updateExistingPivot($guardian->id, $pivotAttributes);
            } else {
                $student->guardians()->attach($guardian->id, $pivotAttributes);
            }
        }

        $alreadyLinked ? $this->updatedCount++ : $this->attachedCount++;
    }

    private function parseBoolean(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'yes', 'y', 'true'], true);
    }

    public function importedCount(): int
    {
        return $this->attachedCount;
    }

    public function updatedCount(): int
    {
        return $this->updatedCount;
    }

    public function rules(): array
    {
        return [
            'student_admission_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100', new ValidName],
            'last_name' => ['required', 'string', 'max:100', new ValidName],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'relationship_type' => ['required', Rule::in(['father', 'mother', 'guardian', 'other', 'Father', 'Mother', 'Guardian', 'Other'])],
            'is_primary' => ['nullable'],
            'can_pickup' => ['nullable'],
        ];
    }
}
