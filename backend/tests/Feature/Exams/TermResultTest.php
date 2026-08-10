<?php

namespace Tests\Feature\Exams;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class TermResultTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makePublishedExam(School $school, Term $term, Section $section, Subject $subject, float $weight, int $marksObtained, int $maxMarks, int $enteredBy): ExamSubject
    {
        $exam = Exam::factory()->for($school)->create(['academic_year_id' => $term->academic_year_id, 'term_id' => $term->id, 'weight' => $weight, 'is_published' => true, 'published_at' => now()]);
        $examSubject = ExamSubject::factory()->for($school)->create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id, 'max_marks' => $maxMarks]);

        return $examSubject;
    }

    public function test_term_result_computes_a_weighted_average_across_published_exams(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create();
        $term = Term::factory()->for($school)->create(['academic_year_id' => $year->id]);
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        $subject = Subject::factory()->for($school)->create();
        $student = Student::factory()->for($school)->create(['academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id]);

        // Midterm: weight 1, 60/100 = 60%. Final: weight 2, 90/100 = 90%.
        // Weighted average = (60*1 + 90*2) / 3 = 80.
        $midtermSubject = $this->makePublishedExam($school, $term, $section, $subject, 1, 60, 100, $admin->id);
        ExamMark::factory()->for($school)->create(['exam_subject_id' => $midtermSubject->id, 'student_id' => $student->id, 'marks_obtained' => 60, 'entered_by' => $admin->id]);

        $finalSubject = $this->makePublishedExam($school, $term, $section, $subject, 2, 90, 100, $admin->id);
        ExamMark::factory()->for($school)->create(['exam_subject_id' => $finalSubject->id, 'student_id' => $student->id, 'marks_obtained' => 90, 'entered_by' => $admin->id]);

        $response = $this->actingAsInSchool($admin)->getJson("/api/v1/terms/{$term->id}/result?student_id={$student->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.exams')
            ->assertJsonPath('data.weighted_percentage', 80);
    }

    public function test_term_result_ranks_student_among_section_peers(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create();
        $term = Term::factory()->for($school)->create(['academic_year_id' => $year->id]);
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        $subject = Subject::factory()->for($school)->create();

        $topStudent = Student::factory()->for($school)->create(['academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id]);
        $midStudent = Student::factory()->for($school)->create(['academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id]);

        $exam = Exam::factory()->for($school)->create(['academic_year_id' => $year->id, 'term_id' => $term->id, 'weight' => 1, 'is_published' => true, 'published_at' => now()]);
        $examSubject = ExamSubject::factory()->for($school)->create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id, 'max_marks' => 100]);

        ExamMark::factory()->for($school)->create(['exam_subject_id' => $examSubject->id, 'student_id' => $topStudent->id, 'marks_obtained' => 95, 'entered_by' => $admin->id]);
        ExamMark::factory()->for($school)->create(['exam_subject_id' => $examSubject->id, 'student_id' => $midStudent->id, 'marks_obtained' => 70, 'entered_by' => $admin->id]);

        $response = $this->actingAsInSchool($admin)->getJson("/api/v1/terms/{$term->id}/result?student_id={$midStudent->id}");

        $response->assertOk()
            ->assertJsonPath('data.rank.position', 2)
            ->assertJsonPath('data.rank.out_of', 2);
    }

    public function test_parent_can_view_child_term_result(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $parentUser = $this->createUserWithRole($school, 'Parent');
        $guardian = Guardian::factory()->for($school)->create(['user_id' => $parentUser->id]);
        $year = AcademicYear::factory()->for($school)->create();
        $term = Term::factory()->for($school)->create(['academic_year_id' => $year->id]);
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        $subject = Subject::factory()->for($school)->create();
        $student = Student::factory()->for($school)->create(['academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id]);
        $guardian->students()->attach($student->id, ['relationship_type' => 'mother', 'is_primary' => true]);

        $exam = Exam::factory()->for($school)->create(['academic_year_id' => $year->id, 'term_id' => $term->id, 'is_published' => true, 'published_at' => now()]);
        $examSubject = ExamSubject::factory()->for($school)->create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id, 'max_marks' => 100]);
        ExamMark::factory()->for($school)->create(['exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 75, 'entered_by' => $admin->id]);

        $response = $this->actingAsInSchool($parentUser)->getJson("/api/v1/parent/children/{$student->id}/term-result?term_id={$term->id}");

        $response->assertOk()->assertJsonPath('data.weighted_percentage', 75);
    }
}
