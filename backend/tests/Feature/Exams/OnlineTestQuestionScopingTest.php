<?php

namespace Tests\Feature\Exams;

use App\Models\AcademicYear;
use App\Models\ClassSubjectTeacher;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * Regression coverage for the fix to a real reported bug: opening "Configure
 * Test" for a brand-new online exam subject was showing every question ever
 * created for that Subject app-wide (a leftover of the old subject-scoped
 * Question Bank), not just what belonged to this specific test. A question
 * now only appears for a test once it's created or imported directly into
 * it — see QuestionController::attachToTest() / McqQuestionsImport's
 * optional exam-subject param.
 */
class OnlineTestQuestionScopingTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeOnlineExamSubject(School $school, array $attrs = []): ExamSubject
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

        return ExamSubject::factory()->create([
            'exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'max_marks' => 10, 'is_online' => true, 'max_attempts' => 1, ...$attrs,
        ]);
    }

    private function storeImportFile(array $rows, string $name = 'question-import-scoping-test.xlsx'): UploadedFile
    {
        Excel::store(new class($rows) implements FromArray, WithHeadings
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'marks', 'negative_marks'];
            }
        }, $name, 'local');

        return new UploadedFile(
            Storage::disk('local')->path($name), $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true,
        );
    }

    public function test_a_freshly_configured_online_test_has_no_questions_until_something_is_attached(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $examSubject = $this->makeOnlineExamSubject($school);

        $response = $this->actingAsInSchool($admin)->getJson("/api/v1/exam-subjects/{$examSubject->id}/online-test-questions");

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_creating_a_question_with_exam_subject_id_attaches_it_to_that_test_only(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $examSubjectA = $this->makeOnlineExamSubject($school);
        $examSubjectB = $this->makeOnlineExamSubject($school, ['subject_id' => $examSubjectA->subject_id]);

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/questions', [
            'exam_subject_id' => $examSubjectA->id,
            'subject_id' => $examSubjectA->subject_id,
            'type' => 'mcq',
            'text' => 'What is 2+2?',
            'default_marks' => 2,
            'options' => [
                ['option_text' => '3', 'is_correct' => false],
                ['option_text' => '4', 'is_correct' => true],
            ],
        ]);
        $response->assertCreated();
        $questionId = $response->json('data.id');

        $this->assertDatabaseHas('online_test_questions', ['exam_subject_id' => $examSubjectA->id, 'question_id' => $questionId]);

        $this->actingAsInSchool($admin)->getJson("/api/v1/exam-subjects/{$examSubjectA->id}/online-test-questions")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $questionId);

        // Same subject, different test — must not see a question attached elsewhere.
        $this->actingAsInSchool($admin)->getJson("/api/v1/exam-subjects/{$examSubjectB->id}/online-test-questions")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_importing_with_exam_subject_id_attaches_every_imported_question_to_that_test(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $examSubject = $this->makeOnlineExamSubject($school);
        $file = $this->storeImportFile([
            ['What is 2+2?', '3', '4', '5', '6', 'B', 2, 0.5],
            ['The sky is blue.', 'True', 'False', '', '', 'A', 1, 0],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/questions/import', [
            'file' => $file, 'subject_id' => $examSubject->subject_id, 'exam_subject_id' => $examSubject->id,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.imported_count'));

        $this->actingAsInSchool($admin)->getJson("/api/v1/exam-subjects/{$examSubject->id}/online-test-questions")
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_teacher_not_assigned_to_the_subject_cannot_attach_a_question_into_someone_elses_test(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher'); // holds questions.create + online-exams.configure, but isn't assigned below
        $examSubject = $this->makeOnlineExamSubject($school);

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/questions', [
            'exam_subject_id' => $examSubject->id,
            'subject_id' => $examSubject->subject_id,
            'type' => 'mcq',
            'text' => 'Should not be allowed',
            'default_marks' => 1,
            'options' => [
                ['option_text' => 'A', 'is_correct' => true],
                ['option_text' => 'B', 'is_correct' => false],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_a_teacher_assigned_to_the_subject_can_attach_a_question_into_their_own_test(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $examSubject = $this->makeOnlineExamSubject($school);

        tenancy()->initialize($school);
        ClassSubjectTeacher::query()->create([
            'academic_year_id' => AcademicYear::query()->first()->id,
            'section_id' => $examSubject->section_id, 'subject_id' => $examSubject->subject_id, 'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/questions', [
            'exam_subject_id' => $examSubject->id,
            'subject_id' => $examSubject->subject_id,
            'type' => 'mcq',
            'text' => 'Allowed for the assigned teacher',
            'default_marks' => 1,
            'options' => [
                ['option_text' => 'A', 'is_correct' => true],
                ['option_text' => 'B', 'is_correct' => false],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('online_test_questions', ['exam_subject_id' => $examSubject->id, 'question_id' => $response->json('data.id')]);
    }
}
