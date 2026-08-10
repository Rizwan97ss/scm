<?php

namespace App\Imports;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentEnrollmentService;
use App\Services\StudentIdGeneratorService;
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
    use Importable, SkipsFailures;

    private int $importedCount = 0;

    public function __construct(
        private readonly School $school,
        private readonly AcademicYear $academicYear,
        private readonly User $performedBy,
        private readonly StudentIdGeneratorService $idGenerator,
        private readonly StudentEnrollmentService $enrollment,
    ) {}

    public function onRow(Row $row): void
    {
        $data = $row->toCollection();

        $gradeLevel = GradeLevel::query()->where('school_id', $this->school->id)->where('code', $data['grade_level_code'])->first();
        $section = Section::query()
            ->where('school_id', $this->school->id)
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

        $student = Student::query()->create([
            'school_id' => $this->school->id,
            'admission_number' => $this->idGenerator->generate($this->school, now()),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => strtolower($data['gender']),
            'date_of_birth' => $data['date_of_birth'],
            'blood_group' => $data['blood_group'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
            'academic_year_id' => $this->academicYear->id,
            'roll_number' => $data['roll_number'] ?? null,
            'admission_date' => $data['admission_date'] ?? now()->toDateString(),
            'status' => 'active',
            'previous_school_name' => $data['previous_school_name'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
        ]);

        $this->enrollment->recordAdmission($student, $this->performedBy);

        $this->importedCount++;
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female,other,Male,Female,Other'],
            'date_of_birth' => ['required', 'date'],
            'grade_level_code' => ['required', 'string'],
            'section_name' => ['required', 'string'],
        ];
    }
}
