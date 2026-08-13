<?php

namespace Tests\Feature\Exams;

use App\Models\AcademicYear;
use App\Models\AssessmentComponentType;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\ExamSubjectGroup;
use App\Models\GradeBand;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * Covers what ExamTest.php's single-component tests don't: a subject with
 * several gradable components combining into one total (SubjectResultService),
 * the group-level publish/unpublish declare workflow and its authorization
 * (ExamController::assertCanPublishGroup()), and the partial-absence rule.
 */
class ExamSubjectGroupResultTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    /**
     * @param  array<int, array{name: string, max_marks: float}>  $componentSpecs
     * @return array{0: Exam, 1: ExamSubjectGroup, 2: array<int, ExamSubject>, 3: Student, 4: Section}
     */
    private function makeMultiComponentSubject(School $school, array $componentSpecs, ?int $classTeacherId = null): array
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create([
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'class_teacher_id' => $classTeacherId,
        ]);
        tenancy()->initialize($school);
        $subject = Subject::factory()->create();
        tenancy()->initialize($school);
        $exam = Exam::factory()->create(['academic_year_id' => $year->id]);

        $components = [];
        foreach ($componentSpecs as $spec) {
            tenancy()->initialize($school);
            $componentType = AssessmentComponentType::factory()->create(['name' => $spec['name']]);
            tenancy()->initialize($school);
            $components[] = ExamSubject::factory()->create([
                'exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
                'assessment_component_type_id' => $componentType->id, 'max_marks' => $spec['max_marks'],
            ]);
        }

        $group = $components[0]->examSubjectGroup;

        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
        ]);

        return [$exam, $group, $components, $student, $section];
    }

    /** The worked example from the feature request: Online MCQ 17/20, Written 48/60, Oral 8/10, Practical 9/10 → 82/100. */
    public function test_multi_component_subject_computes_combined_total_percentage_and_grade(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, $group, $components, $student] = $this->makeMultiComponentSubject($school, [
            ['name' => 'Online MCQ', 'max_marks' => 20],
            ['name' => 'Written', 'max_marks' => 60],
            ['name' => 'Oral', 'max_marks' => 10],
            ['name' => 'Practical', 'max_marks' => 10],
        ]);

        tenancy()->initialize($school);
        $scale = GradingScale::factory()->create(['is_default' => true]);
        tenancy()->initialize($school);
        GradeBand::factory()->create(['grading_scale_id' => $scale->id, 'min_percentage' => 80, 'max_percentage' => 100, 'grade_label' => 'A', 'grade_point' => 4.0]);
        tenancy()->initialize($school);
        GradeBand::factory()->create(['grading_scale_id' => $scale->id, 'min_percentage' => 0, 'max_percentage' => 79.99, 'grade_label' => 'B', 'grade_point' => 3.0]);
        tenancy()->initialize($school);
        $group->update(['passing_marks' => 40]);

        $obtained = [17, 48, 8, 9];
        foreach ($components as $i => $component) {
            tenancy()->initialize($school);
            ExamMark::factory()->create([
                'exam_subject_id' => $component->id, 'student_id' => $student->id, 'marks_obtained' => $obtained[$i], 'entered_by' => $admin->id,
            ]);
        }

        $response = $this->actingAsInSchool($admin)->getJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/result?student_id={$student->id}");

        $response->assertOk()
            ->assertJsonPath('data.max_marks_total', 100)
            ->assertJsonPath('data.marks_obtained_total', 82)
            ->assertJsonPath('data.percentage', 82)
            ->assertJsonPath('data.grade_label', 'A')
            ->assertJsonPath('data.is_pass', true);
    }

    /** Confirmed rule: a missing/absent component contributes 0 to the total, it doesn't block the whole subject. */
    public function test_a_missing_component_contributes_zero_without_marking_the_whole_subject_absent(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, $group, $components, $student] = $this->makeMultiComponentSubject($school, [
            ['name' => 'Written', 'max_marks' => 60],
            ['name' => 'Oral', 'max_marks' => 10],
        ]);

        tenancy()->initialize($school);
        ExamMark::factory()->create(['exam_subject_id' => $components[0]->id, 'student_id' => $student->id, 'marks_obtained' => 48, 'entered_by' => $admin->id]);
        tenancy()->initialize($school);
        ExamMark::factory()->create(['exam_subject_id' => $components[1]->id, 'student_id' => $student->id, 'is_absent' => true, 'marks_obtained' => null, 'entered_by' => $admin->id]);

        $response = $this->actingAsInSchool($admin)->getJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/result?student_id={$student->id}");

        $response->assertOk()
            ->assertJsonPath('data.marks_obtained_total', 48)
            ->assertJsonPath('data.is_absent', false);
    }

    public function test_subject_is_absent_only_when_every_component_is_absent(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, $group, $components, $student] = $this->makeMultiComponentSubject($school, [
            ['name' => 'Written', 'max_marks' => 60],
            ['name' => 'Oral', 'max_marks' => 10],
        ]);

        foreach ($components as $component) {
            tenancy()->initialize($school);
            ExamMark::factory()->create(['exam_subject_id' => $component->id, 'student_id' => $student->id, 'is_absent' => true, 'marks_obtained' => null, 'entered_by' => $admin->id]);
        }

        $response = $this->actingAsInSchool($admin)->getJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/result?student_id={$student->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_absent', true)
            ->assertJsonPath('data.marks_obtained_total', null)
            ->assertJsonPath('data.percentage', null);
    }

    public function test_class_teacher_can_publish_their_own_sections_subject_group(): void
    {
        $school = $this->createSchool();
        $classTeacher = $this->createUserWithRole($school, 'Class Teacher');
        [$exam, $group] = $this->makeMultiComponentSubject($school, [['name' => 'Written', 'max_marks' => 100]], classTeacherId: $classTeacher->id);

        $response = $this->actingAsInSchool($classTeacher)->postJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/publish");

        $response->assertOk();
        tenancy()->initialize($school);
        $fresh = $group->fresh();
        $this->assertNotNull($fresh->published_at);
        $this->assertSame($classTeacher->id, $fresh->published_by);
    }

    public function test_class_teacher_cannot_publish_a_subject_group_for_a_section_they_do_not_teach(): void
    {
        $school = $this->createSchool();
        $classTeacher = $this->createUserWithRole($school, 'Class Teacher');
        // No classTeacherId passed — this section belongs to nobody's roster.
        [$exam, $group] = $this->makeMultiComponentSubject($school, [['name' => 'Written', 'max_marks' => 100]]);

        $response = $this->actingAsInSchool($classTeacher)->postJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/publish");

        $response->assertStatus(403);
    }

    /** Being the section's class_teacher_id isn't enough on its own — the role itself must hold exam-marks.publish, which plain Teacher never does. */
    public function test_teacher_without_the_publish_permission_cannot_publish_a_subject_group(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$exam, $group] = $this->makeMultiComponentSubject($school, [['name' => 'Written', 'max_marks' => 100]], classTeacherId: $teacher->id);

        $response = $this->actingAsInSchool($teacher)->postJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/publish");

        $response->assertStatus(403);
    }

    public function test_admin_can_publish_a_subject_group_regardless_of_section(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, $group] = $this->makeMultiComponentSubject($school, [['name' => 'Written', 'max_marks' => 100]]);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/publish");

        $response->assertOk();
    }

    /** The core of requirement 3: a Class Teacher declares one subject early, independent of the whole exam. */
    public function test_student_sees_the_full_breakdown_once_their_subject_group_is_individually_published(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$exam, $group, $components, $student] = $this->makeMultiComponentSubject($school, [
            ['name' => 'Written', 'max_marks' => 60],
            ['name' => 'Oral', 'max_marks' => 10],
        ]);
        $student->update(['user_id' => $studentUser->id]);

        tenancy()->initialize($school);
        ExamMark::factory()->create(['exam_subject_id' => $components[0]->id, 'student_id' => $student->id, 'marks_obtained' => 48, 'entered_by' => $admin->id]);
        tenancy()->initialize($school);
        ExamMark::factory()->create(['exam_subject_id' => $components[1]->id, 'student_id' => $student->id, 'marks_obtained' => 8, 'entered_by' => $admin->id]);

        $this->actingAsInSchool($admin)->postJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/publish")->assertOk();

        $response = $this->actingAsInSchool($studentUser)->getJson("/api/v1/exams/{$exam->id}/report-card?student_id={$student->id}");

        $response->assertOk()
            ->assertJsonPath('data.exam.is_published', false) // whole exam still undeclared
            ->assertJsonPath('data.subjects.0.group.status', 'published')
            ->assertJsonPath('data.subjects.0.marks_obtained_total', 56);
    }

    public function test_unpublishing_a_subject_group_masks_it_from_the_student_again(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$exam, $group, $components, $student] = $this->makeMultiComponentSubject($school, [['name' => 'Written', 'max_marks' => 100]]);
        $student->update(['user_id' => $studentUser->id]);

        tenancy()->initialize($school);
        ExamMark::factory()->create(['exam_subject_id' => $components[0]->id, 'student_id' => $student->id, 'marks_obtained' => 70, 'entered_by' => $admin->id]);

        $this->actingAsInSchool($admin)->postJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/publish")->assertOk();
        $this->actingAsInSchool($admin)->postJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/unpublish")->assertOk();

        $response = $this->actingAsInSchool($studentUser)->getJson("/api/v1/exams/{$exam->id}/report-card?student_id={$student->id}");

        $response->assertOk()
            ->assertJsonPath('data.subjects.0.group.status', 'calculated')
            ->assertJsonPath('data.subjects.0.marks_obtained_total', null);
    }

    public function test_destroying_a_component_removes_it_and_cascades_its_marks(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, $group, $components, $student] = $this->makeMultiComponentSubject($school, [
            ['name' => 'Written', 'max_marks' => 60],
            ['name' => 'Oral', 'max_marks' => 10],
        ]);

        tenancy()->initialize($school);
        ExamMark::factory()->create(['exam_subject_id' => $components[1]->id, 'student_id' => $student->id, 'marks_obtained' => 8, 'entered_by' => $admin->id]);

        $response = $this->actingAsInSchool($admin)->deleteJson("/api/v1/exams/{$exam->id}/exam-subject-groups/{$group->id}/components/{$components[1]->id}");

        // ApiResponse::noContent() still wraps the same {success,message,data} envelope every
        // other endpoint uses — always 200, never a literal 204 (see ApiResponse::noContent()).
        $response->assertOk();
        $this->assertDatabaseMissing('exam_subjects', ['id' => $components[1]->id]);
        $this->assertDatabaseMissing('exam_marks', ['exam_subject_id' => $components[1]->id]);
        $this->assertDatabaseHas('exam_subjects', ['id' => $components[0]->id]);
    }
}
