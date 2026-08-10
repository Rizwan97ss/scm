<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentEnrollmentService;
use App\Services\StudentIdGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class DemoStudentSeeder extends Seeder
{
    public function run(StudentIdGeneratorService $idGenerator, StudentEnrollmentService $enrollment): void
    {
        $school = School::query()->where('short_name', 'demo')->firstOrFail();
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        $year = AcademicYear::query()->where('school_id', $school->id)->where('is_current', true)->firstOrFail();
        $performedBy = User::query()->where('email', 'admin@riverside-demo.test')->firstOrFail();

        $gina = $this->guardian($school, 'Gina', 'Guardian', 'gina.guardian@example.com', invited: true);
        $george = $this->guardian($school, 'George', 'Guardian', 'george.guardian@example.com', invited: false);
        $greg = $this->guardian($school, 'Greg', 'Guardian', 'greg.guardian@example.com', invited: true);
        $grace = $this->guardian($school, 'Grace', 'Guardian', 'grace.guardian@example.com', invited: false);

        $section1A = Section::query()->where('school_id', $school->id)->whereHas('gradeLevel', fn ($q) => $q->where('code', 'G1'))->where('name', 'A')->firstOrFail();
        $section1B = Section::query()->where('school_id', $school->id)->whereHas('gradeLevel', fn ($q) => $q->where('code', 'G1'))->where('name', 'B')->firstOrFail();
        $section2A = Section::query()->where('school_id', $school->id)->whereHas('gradeLevel', fn ($q) => $q->where('code', 'G2'))->where('name', 'A')->firstOrFail();

        $sam = $this->student($school, $idGenerator, $enrollment, $performedBy, 'Sam', 'Student', 'male', '2019-04-12', $section1A, $year);
        $this->link($sam, $gina, 'mother', true);
        $this->link($sam, $george, 'father', false);

        $sally = $this->student($school, $idGenerator, $enrollment, $performedBy, 'Sally', 'Student', 'female', '2019-09-03', $section1A, $year);
        $this->link($sally, $gina, 'mother', true);
        $this->link($sally, $george, 'father', false);

        $mia = $this->student($school, $idGenerator, $enrollment, $performedBy, 'Mia', 'Martinez', 'female', '2019-01-22', $section1B, $year);
        $this->link($mia, $gina, 'guardian', true);

        $max = $this->student($school, $idGenerator, $enrollment, $performedBy, 'Max', 'Miles', 'male', '2018-06-30', $section2A, $year);
        $this->link($max, $greg, 'father', true);

        $zoe = $this->student($school, $idGenerator, $enrollment, $performedBy, 'Zoe', 'Zimmerman', 'female', '2018-11-15', $section2A, $year);
        $this->link($zoe, $grace, 'mother', true);

        $this->command?->info('Demo guardians seeded (password for invited ones: "password"): gina.guardian@example.com, greg.guardian@example.com');
    }

    private function guardian(School $school, string $first, string $last, string $email, bool $invited): Guardian
    {
        $user = null;

        if ($invited) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'school_id' => $school->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->hasRole('Parent')) {
                $user->assignRole('Parent');
            }
        }

        return Guardian::query()->updateOrCreate(
            ['school_id' => $school->id, 'email' => $email],
            [
                'user_id' => $user?->id,
                'first_name' => $first,
                'last_name' => $last,
                'phone' => '+1-555-01'.random_int(10, 99),
                'invited_at' => $invited ? now() : null,
            ]
        );
    }

    private function student(
        School $school,
        StudentIdGeneratorService $idGenerator,
        StudentEnrollmentService $enrollment,
        User $performedBy,
        string $first,
        string $last,
        string $gender,
        string $dateOfBirth,
        Section $section,
        AcademicYear $year,
    ): Student {
        $existing = Student::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('first_name', $first)
            ->where('last_name', $last)
            ->where('date_of_birth', $dateOfBirth)
            ->first();

        if ($existing) {
            return $existing;
        }

        $admissionDate = now()->subMonths(2);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'admission_number' => $idGenerator->generate($school, $admissionDate),
            'first_name' => $first,
            'last_name' => $last,
            'gender' => $gender,
            'date_of_birth' => $dateOfBirth,
            'current_grade_level_id' => $section->grade_level_id,
            'current_section_id' => $section->id,
            'academic_year_id' => $year->id,
            'admission_date' => $admissionDate->toDateString(),
            'status' => 'active',
            'emergency_contact_name' => "{$first} Emergency Contact",
            'emergency_contact_phone' => '+1-555-0199',
        ]);

        $enrollment->recordAdmission($student, $performedBy, $admissionDate);

        return $student;
    }

    private function link(Student $student, Guardian $guardian, string $relationship, bool $isPrimary): void
    {
        if (! $student->guardians()->where('guardian_id', $guardian->id)->exists()) {
            $student->guardians()->attach($guardian->id, [
                'relationship_type' => $relationship,
                'is_primary' => $isPrimary,
                'can_pickup' => true,
            ]);
        }
    }
}
