<?php

namespace Tests\Feature\Students;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class StudentAdmissionTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_admin_can_admit_a_student_with_a_new_guardian(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/students', [
            'first_name' => 'Sam',
            'last_name' => 'Sample',
            'gender' => 'male',
            'date_of_birth' => '2018-01-15',
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
            'admission_date' => now()->toDateString(),
            'guardians' => [
                [
                    'first_name' => 'Gina',
                    'last_name' => 'Sample',
                    'phone' => '+1-555-0100',
                    'email' => 'gina@example.com',
                    'relationship_type' => 'mother',
                    'is_primary' => true,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'Sam')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.guardians.0.full_name', 'Gina Sample');

        $this->assertNotEmpty($response->json('data.admission_number'));
        $this->assertDatabaseHas('student_enrollment_histories', ['action' => 'admission']);
        $this->assertDatabaseHas('guardians', ['email' => 'gina@example.com']);
    }

    /**
     * The actual regression this guards against: two siblings admitted
     * separately (not via the bulk import, which already reuses a guardian
     * by email) with the same guardian email must produce one Guardian row,
     * not two duplicates — matching StudentsImport/GuardiansImport's
     * existing email-reuse rule.
     */
    public function test_admitting_two_siblings_separately_reuses_the_same_guardian_by_email(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);

        $admit = fn (string $firstName) => $this->actingAsInSchool($admin)->postJson('/api/v1/students', [
            'first_name' => $firstName,
            'last_name' => 'Sample',
            'gender' => 'male',
            'date_of_birth' => '2018-01-15',
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
            'admission_date' => now()->toDateString(),
            'guardians' => [
                [
                    'first_name' => 'Shared',
                    'last_name' => 'Parent',
                    'phone' => '+1-555-0100',
                    'email' => 'shared.parent@example.com',
                    'relationship_type' => 'mother',
                    'is_primary' => true,
                ],
            ],
        ]);

        $admit('Sibling1')->assertCreated();
        $admit('Sibling2')->assertCreated();

        $this->assertDatabaseCount('guardians', 1);
    }

    public function test_a_student_name_containing_junk_special_characters_is_rejected(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/students', [
            'first_name' => '@@#@',
            'last_name' => 'Sample',
            'gender' => 'male',
            'date_of_birth' => '2018-01-15',
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
            'admission_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('first_name');
    }

    public function test_admission_number_is_sequential_and_formatted(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);

        $payload = fn (string $first) => [
            'first_name' => $first,
            'last_name' => 'Sample',
            'gender' => 'female',
            'date_of_birth' => '2018-01-15',
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
            'admission_date' => now()->toDateString(),
        ];

        $first = $this->actingAsInSchool($admin)->postJson('/api/v1/students', $payload('One'))->json('data.admission_number');
        $second = $this->actingAsInSchool($admin)->postJson('/api/v1/students', $payload('Two'))->json('data.admission_number');

        $year = now()->year;
        $this->assertEquals("{$year}-0001", $first);
        $this->assertEquals("{$year}-0002", $second);
    }

    public function test_teacher_can_only_see_students_in_their_assigned_sections(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $otherTeacher = $this->createUserWithRole($school, 'Teacher');
        $admin = $this->createUserWithRole($school, 'School Admin');

        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $mySection = Section::factory()->create([
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'name' => 'A', 'class_teacher_id' => $teacher->id,
        ]);
        tenancy()->initialize($school);
        $otherSection = Section::factory()->create([
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'name' => 'B', 'class_teacher_id' => $otherTeacher->id,
        ]);

        $admitTo = function ($section, $firstName) use ($admin, $year, $gradeLevel) {
            return $this->actingAsInSchool($admin)->postJson('/api/v1/students', [
                'first_name' => $firstName, 'last_name' => 'Student', 'gender' => 'male', 'date_of_birth' => '2018-01-15',
                'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
                'admission_date' => now()->toDateString(),
            ]);
        };

        $admitTo($mySection, 'MyStudent')->assertCreated();
        $admitTo($otherSection, 'OtherStudent')->assertCreated();

        $response = $this->actingAsInSchool($teacher)->getJson('/api/v1/students?per_page=50');
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('first_name');
        $this->assertTrue($names->contains('MyStudent'));
        $this->assertFalse($names->contains('OtherStudent'));
    }
}
