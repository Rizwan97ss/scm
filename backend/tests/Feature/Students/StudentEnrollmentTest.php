<?php

namespace Tests\Feature\Students;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class StudentEnrollmentTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function admittedStudent($school, $year, $gradeLevel, $section): Student
    {
        return Student::factory()->for($school)->create([
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
            'status' => 'active',
        ]);
    }

    public function test_full_student_lifecycle_promote_transfer_withdraw_graduate_reactivate(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create();
        $nextYear = AcademicYear::factory()->for($school)->create();
        $gradeLevel1 = GradeLevel::factory()->for($school)->create(['sequence' => 1]);
        $gradeLevel2 = GradeLevel::factory()->for($school)->create(['sequence' => 2]);
        $section1 = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel1->id]);
        $section2 = Section::factory()->for($school)->create(['academic_year_id' => $nextYear->id, 'grade_level_id' => $gradeLevel2->id]);

        $student = $this->admittedStudent($school, $year, $gradeLevel1, $section1);

        // Promote
        $promote = $this->actingAsInSchool($admin)->postJson("/api/v1/students/{$student->id}/promote", [
            'to_grade_level_id' => $gradeLevel2->id,
            'to_section_id' => $section2->id,
            'to_academic_year_id' => $nextYear->id,
        ]);
        $promote->assertOk()->assertJsonPath('data.status', 'active');
        $student->refresh();
        $this->assertEquals($gradeLevel2->id, $student->current_grade_level_id);
        $this->assertEquals($nextYear->id, $student->academic_year_id);

        // Withdraw
        $withdraw = $this->actingAsInSchool($admin)->postJson("/api/v1/students/{$student->id}/withdraw", ['reason' => 'Family relocation']);
        $withdraw->assertOk()->assertJsonPath('data.status', 'withdrawn');

        // Reactivate
        $reactivate = $this->actingAsInSchool($admin)->postJson("/api/v1/students/{$student->id}/reactivate", [
            'to_grade_level_id' => $gradeLevel2->id,
            'to_section_id' => $section2->id,
        ]);
        $reactivate->assertOk()->assertJsonPath('data.status', 'active');

        // Graduate
        $graduate = $this->actingAsInSchool($admin)->postJson("/api/v1/students/{$student->id}/graduate");
        $graduate->assertOk()->assertJsonPath('data.status', 'graduated');

        $history = $this->actingAsInSchool($admin)->getJson("/api/v1/students/{$student->id}/enrollment-history");
        $history->assertOk();
        $actions = collect($history->json('data'))->pluck('action');
        $this->assertEqualsCanonicalizing(['promotion', 'withdrawal', 'reactivation', 'graduation'], $actions->all());
    }

    public function test_transfer_out_clears_current_section(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        $student = $this->admittedStudent($school, $year, $gradeLevel, $section);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/students/{$student->id}/transfer", ['reason' => 'Moving abroad']);

        $response->assertOk()->assertJsonPath('data.status', 'transferred_out');
        $this->assertNull($student->fresh()->current_section_id);
    }

    public function test_teacher_cannot_promote_a_student(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'class_teacher_id' => $teacher->id]);
        $student = $this->admittedStudent($school, $year, $gradeLevel, $section);

        $response = $this->actingAsInSchool($teacher)->postJson("/api/v1/students/{$student->id}/promote", [
            'to_grade_level_id' => $gradeLevel->id,
            'to_section_id' => $section->id,
            'to_academic_year_id' => $year->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_bulk_promote_moves_multiple_students(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create();
        $nextYear = AcademicYear::factory()->for($school)->create();
        $gradeLevel1 = GradeLevel::factory()->for($school)->create(['sequence' => 1]);
        $gradeLevel2 = GradeLevel::factory()->for($school)->create(['sequence' => 2]);
        $section1 = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel1->id]);
        $section2 = Section::factory()->for($school)->create(['academic_year_id' => $nextYear->id, 'grade_level_id' => $gradeLevel2->id]);

        $studentA = $this->admittedStudent($school, $year, $gradeLevel1, $section1);
        $studentB = $this->admittedStudent($school, $year, $gradeLevel1, $section1);

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/students/bulk/promote', [
            'student_ids' => [$studentA->id, $studentB->id],
            'to_grade_level_id' => $gradeLevel2->id,
            'to_section_id' => $section2->id,
            'to_academic_year_id' => $nextYear->id,
        ]);

        $response->assertOk()->assertJsonPath('data.promoted_count', 2);
        $this->assertEquals($gradeLevel2->id, $studentA->fresh()->current_grade_level_id);
        $this->assertEquals($gradeLevel2->id, $studentB->fresh()->current_grade_level_id);
    }
}
