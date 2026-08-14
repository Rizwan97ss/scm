<?php

namespace Tests\Feature\Exams;

use App\Models\AcademicYear;
use App\Models\ClassSubjectTeacher;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class OnlineExamTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeOnlineExamSubject(School $school, ?int $teacherId = null, array $attrs = []): array
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        tenancy()->initialize($school);
        $subject = Subject::factory()->create();
        tenancy()->initialize($school);
        $exam = Exam::factory()->create(['academic_year_id' => $year->id]);
        tenancy()->initialize($school);
        $examSubject = ExamSubject::factory()->create([
            'exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'max_marks' => 10, 'is_online' => true, 'max_attempts' => 1, ...$attrs,
        ]);

        if ($teacherId) {
            ClassSubjectTeacher::query()->create([
                'academic_year_id' => $year->id,
                'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherId,
            ]);
        }

        tenancy()->initialize($school);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Student');
        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
            'user_id' => $studentUser->id,
        ]);

        return [$examSubject, $student, $studentUser];
    }

    private function makeQuestion(School $school, int $createdBy, int $correctIndex = 0, float $marks = 5): Question
    {
        tenancy()->initialize($school);
        $question = Question::factory()->create(['created_by' => $createdBy, 'type' => 'mcq', 'default_marks' => $marks]);

        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            tenancy()->initialize($school);
            QuestionOption::factory()->create([
                'question_id' => $question->id, 'option_text' => $label, 'is_correct' => $i === $correctIndex, 'sequence' => $i,
            ]);
        }

        return $question;
    }

    public function test_admin_can_create_a_question_with_exactly_one_correct_option(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/questions', [
            'type' => 'true_false',
            'text' => 'The sky is blue.',
            'default_marks' => 2,
            'options' => [
                ['option_text' => 'True', 'is_correct' => true],
                ['option_text' => 'False', 'is_correct' => false],
            ],
        ]);

        $response->assertCreated()->assertJsonCount(2, 'data.options');
        $this->assertDatabaseHas('questions', ['text' => 'The sky is blue.']);
    }

    public function test_question_must_have_exactly_one_correct_option(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/questions', [
            'type' => 'mcq',
            'text' => 'Pick one',
            'options' => [
                ['option_text' => 'A', 'is_correct' => true],
                ['option_text' => 'B', 'is_correct' => true],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('options');
    }

    public function test_teacher_can_configure_online_test_questions_for_a_subject_they_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$examSubject] = $this->makeOnlineExamSubject($school, $teacher->id);
        $question = $this->makeQuestion($school, $teacher->id);

        $response = $this->actingAsInSchool($teacher)->postJson("/api/v1/exam-subjects/{$examSubject->id}/online-test-questions", [
            'questions' => [['question_id' => $question->id, 'marks' => 5]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('online_test_questions', ['exam_subject_id' => $examSubject->id, 'question_id' => $question->id]);
    }

    public function test_teacher_cannot_configure_online_test_for_a_subject_they_do_not_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$examSubject] = $this->makeOnlineExamSubject($school); // no teacher assigned
        $question = $this->makeQuestion($school, $teacher->id);

        $response = $this->actingAsInSchool($teacher)->postJson("/api/v1/exam-subjects/{$examSubject->id}/online-test-questions", [
            'questions' => [['question_id' => $question->id]],
        ]);

        $response->assertStatus(403);
    }

    /**
     * Auto-grading still happens instantly (the mark is written to ExamMark
     * the moment the student submits) — but the STUDENT no longer sees it
     * until the subject's result is actually declared, same as every other
     * component type. A staff viewer with exam-marks.view sees it right away.
     */
    public function test_student_is_auto_graded_instantly_but_does_not_see_the_result_until_declared(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$examSubject, $student, $studentUser] = $this->makeOnlineExamSubject($school);

        $q1 = $this->makeQuestion($school, $admin->id, correctIndex: 0, marks: 5); // option A correct
        $q2 = $this->makeQuestion($school, $admin->id, correctIndex: 1, marks: 5); // option B correct

        $this->actingAsInSchool($admin)->postJson("/api/v1/exam-subjects/{$examSubject->id}/online-test-questions", [
            'questions' => [['question_id' => $q1->id], ['question_id' => $q2->id]],
        ])->assertOk();

        $startResponse = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts");
        $startResponse->assertOk();
        $attemptId = $startResponse->json('data.attempt.id');

        // The test-taking view must never leak which option is correct.
        $this->assertArrayNotHasKey('is_correct', $startResponse->json('data.questions.0.options.0') ?? []);

        $correctOptionQ1 = $q1->options()->where('is_correct', true)->first();
        $wrongOptionQ2 = $q2->options()->where('is_correct', false)->first();

        $this->actingAsInSchool($studentUser)->putJson("/api/v1/online-test-attempts/{$attemptId}/answers", [
            'question_id' => $q1->id, 'selected_option_id' => $correctOptionQ1->id,
        ])->assertOk();
        $this->actingAsInSchool($studentUser)->putJson("/api/v1/online-test-attempts/{$attemptId}/answers", [
            'question_id' => $q2->id, 'selected_option_id' => $wrongOptionQ2->id,
        ])->assertOk();

        $submitResponse = $this->actingAsInSchool($studentUser)->postJson("/api/v1/online-test-attempts/{$attemptId}/submit");

        $submitResponse->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonMissingPath('data.score');

        // The mark was still written immediately — only the student's own view of it is masked.
        $this->assertDatabaseHas('exam_marks', [
            'exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 5,
        ]);

        // Staff can see it right away via the same endpoint.
        $this->actingAsInSchool($admin)->getJson("/api/v1/online-test-attempts/{$attemptId}")
            ->assertOk()->assertJsonPath('data.score', 5);

        // Re-fetching as the student themselves still hides it.
        $this->actingAsInSchool($studentUser)->getJson("/api/v1/online-test-attempts/{$attemptId}")
            ->assertOk()->assertJsonMissingPath('data.score');
    }

    public function test_student_cannot_start_an_attempt_for_a_section_they_are_not_in(): void
    {
        $school = $this->createSchool();
        [$examSubject] = $this->makeOnlineExamSubject($school);
        tenancy()->initialize($school);
        $otherStudentUser = User::factory()->create();
        $otherStudentUser->assignRole('Student');
        tenancy()->initialize($school);
        Student::factory()->create(['user_id' => $otherStudentUser->id, 'academic_year_id' => AcademicYear::factory()->create()->id]);

        $response = $this->actingAsInSchool($otherStudentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts");

        $response->assertStatus(422);
    }

    public function test_student_cannot_exceed_max_attempts(): void
    {
        $school = $this->createSchool();
        [$examSubject, , $studentUser] = $this->makeOnlineExamSubject($school, null, ['max_attempts' => 1]);

        $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")->assertOk()
            ->json('data.attempt.id');

        $submitFirst = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")->json('data.attempt.id');
        $this->actingAsInSchool($studentUser)->postJson("/api/v1/online-test-attempts/{$submitFirst}/submit")->assertOk();

        $response = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts");

        $response->assertStatus(422);
    }

    public function test_student_cannot_answer_or_resubmit_after_submitting(): void
    {
        $school = $this->createSchool();
        [$examSubject, , $studentUser] = $this->makeOnlineExamSubject($school);

        $attemptId = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")->json('data.attempt.id');
        $this->actingAsInSchool($studentUser)->postJson("/api/v1/online-test-attempts/{$attemptId}/submit")->assertOk();

        $this->actingAsInSchool($studentUser)->postJson("/api/v1/online-test-attempts/{$attemptId}/submit")->assertStatus(422);
        $this->actingAsInSchool($studentUser)->putJson("/api/v1/online-test-attempts/{$attemptId}/answers", ['question_id' => 1])->assertStatus(422);
    }

    public function test_student_cannot_view_another_students_attempt(): void
    {
        $school = $this->createSchool();
        [$examSubject, , $studentUserA] = $this->makeOnlineExamSubject($school);
        tenancy()->initialize($school);
        $studentUserB = User::factory()->create();
        $studentUserB->assignRole('Student');

        $attemptId = $this->actingAsInSchool($studentUserA)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")->json('data.attempt.id');

        $response = $this->actingAsInSchool($studentUserB)->getJson("/api/v1/online-test-attempts/{$attemptId}");

        $response->assertStatus(403);
    }

    /**
     * A wrong-but-answered question costs its own negative_marks (never a
     * question with no negative_marks set); an unanswered question always
     * scores exactly 0, even one that has negative_marks configured.
     */
    public function test_negative_marking_reduces_score_without_flooring_when_still_positive(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$examSubject, $student, $studentUser] = $this->makeOnlineExamSubject($school);

        $q1 = $this->makeQuestion($school, $admin->id, correctIndex: 0, marks: 5); // no negative_marks — correct, worth +5
        tenancy()->initialize($school);
        $q2 = Question::factory()->create(['created_by' => $admin->id, 'type' => 'mcq', 'default_marks' => 5, 'negative_marks' => 2]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            tenancy()->initialize($school);
            QuestionOption::factory()->create(['question_id' => $q2->id, 'option_text' => $label, 'is_correct' => $i === 1, 'sequence' => $i]);
        }

        $this->actingAsInSchool($admin)->postJson("/api/v1/exam-subjects/{$examSubject->id}/online-test-questions", [
            'questions' => [['question_id' => $q1->id], ['question_id' => $q2->id]],
        ])->assertOk();

        $attemptId = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")->json('data.attempt.id');

        $correctOptionQ1 = $q1->options()->where('is_correct', true)->first();
        $wrongOptionQ2 = $q2->options()->where('is_correct', false)->first();

        $this->actingAsInSchool($studentUser)->putJson("/api/v1/online-test-attempts/{$attemptId}/answers", [
            'question_id' => $q1->id, 'selected_option_id' => $correctOptionQ1->id,
        ])->assertOk();
        $this->actingAsInSchool($studentUser)->putJson("/api/v1/online-test-attempts/{$attemptId}/answers", [
            'question_id' => $q2->id, 'selected_option_id' => $wrongOptionQ2->id,
        ])->assertOk();

        $submitResponse = $this->actingAsInSchool($studentUser)->postJson("/api/v1/online-test-attempts/{$attemptId}/submit");

        // +5 (correct, no negative_marks) + -2 (wrong, negative_marks=2) = 3, no flooring needed.
        // Score isn't in the student's own submit response — see the dedicated OnlineTestResultMaskingTest; the
        // ExamMark row below is the source of truth this test actually needs for the scoring math itself.
        $submitResponse->assertOk()->assertJsonMissingPath('data.score');
        $this->assertDatabaseHas('online_test_answers', ['attempt_id' => $attemptId, 'question_id' => $q1->id, 'marks_awarded' => 5]);
        $this->assertDatabaseHas('online_test_answers', ['attempt_id' => $attemptId, 'question_id' => $q2->id, 'marks_awarded' => -2]);
        $this->assertDatabaseHas('exam_marks', ['exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 3]);
    }

    public function test_negative_marking_total_floors_at_zero_and_never_penalizes_a_blank_answer(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$examSubject, $student, $studentUser] = $this->makeOnlineExamSubject($school);

        tenancy()->initialize($school);
        $q1 = Question::factory()->create(['created_by' => $admin->id, 'type' => 'mcq', 'default_marks' => 5, 'negative_marks' => 2]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            tenancy()->initialize($school);
            QuestionOption::factory()->create(['question_id' => $q1->id, 'option_text' => $label, 'is_correct' => $i === 0, 'sequence' => $i]);
        }
        // q2 is left completely unanswered — must never be penalized even though it has negative_marks configured.
        tenancy()->initialize($school);
        $q2 = Question::factory()->create(['created_by' => $admin->id, 'type' => 'mcq', 'default_marks' => 5, 'negative_marks' => 2]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            tenancy()->initialize($school);
            QuestionOption::factory()->create(['question_id' => $q2->id, 'option_text' => $label, 'is_correct' => $i === 0, 'sequence' => $i]);
        }

        $this->actingAsInSchool($admin)->postJson("/api/v1/exam-subjects/{$examSubject->id}/online-test-questions", [
            'questions' => [['question_id' => $q1->id], ['question_id' => $q2->id]],
        ])->assertOk();

        $attemptId = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")->json('data.attempt.id');

        $wrongOptionQ1 = $q1->options()->where('is_correct', false)->first();
        $this->actingAsInSchool($studentUser)->putJson("/api/v1/online-test-attempts/{$attemptId}/answers", [
            'question_id' => $q1->id, 'selected_option_id' => $wrongOptionQ1->id,
        ])->assertOk();

        $submitResponse = $this->actingAsInSchool($studentUser)->postJson("/api/v1/online-test-attempts/{$attemptId}/submit");

        // Raw total would be -2 (wrong q1) + 0 (blank q2) = -2, floored to 0.
        $submitResponse->assertOk()->assertJsonMissingPath('data.score');
        $this->assertDatabaseHas('online_test_answers', ['attempt_id' => $attemptId, 'question_id' => $q1->id, 'marks_awarded' => -2]);
        $this->assertDatabaseHas('online_test_answers', ['attempt_id' => $attemptId, 'question_id' => $q2->id, 'marks_awarded' => 0]);
        $this->assertDatabaseHas('exam_marks', ['exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 0]);
    }
}
