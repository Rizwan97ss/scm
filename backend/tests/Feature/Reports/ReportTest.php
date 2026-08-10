<?php

namespace Tests\Feature\Reports;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_school_admin_can_view_the_attendance_report(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $section = $this->makeSection($school);
        $student = Student::factory()->for($school)->create(['academic_year_id' => $section->academic_year_id, 'current_section_id' => $section->id]);
        StudentAttendance::factory()->for($school)->create([
            'student_id' => $student->id, 'section_id' => $section->id, 'academic_year_id' => $section->academic_year_id,
            'marked_by' => $admin->id, 'date' => now()->toDateString(), 'status' => 'present',
        ]);

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/reports/attendance');

        $response->assertOk();
        $this->assertNotNull($response->json('data.student'));
        $this->assertNotNull($response->json('data.staff'));
    }

    public function test_receptionist_without_attendance_permissions_cannot_view_the_attendance_report(): void
    {
        $school = $this->createSchool();
        $receptionist = $this->createUserWithRole($school, 'Receptionist');

        $response = $this->actingAsInSchool($receptionist)->getJson('/api/v1/reports/attendance');

        $response->assertStatus(403);
    }

    public function test_teacher_only_sees_the_student_attendance_section_not_staff(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->getJson('/api/v1/reports/attendance');

        $response->assertOk();
        $this->assertNotNull($response->json('data.student'));
        $this->assertNull($response->json('data.staff'));
    }

    public function test_academic_performance_report_computes_average_and_pass_rate(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $section = $this->makeSection($school);
        $scale = GradingScale::factory()->for($school)->create();
        $exam = Exam::factory()->for($school)->create(['academic_year_id' => $section->academic_year_id, 'is_published' => true]);
        $examSubject = ExamSubject::factory()->for($school)->create([
            'exam_id' => $exam->id, 'section_id' => $section->id, 'grading_scale_id' => $scale->id, 'max_marks' => 100, 'passing_marks' => 40,
        ]);
        $studentA = Student::factory()->for($school)->create(['academic_year_id' => $section->academic_year_id, 'current_section_id' => $section->id]);
        $studentB = Student::factory()->for($school)->create(['academic_year_id' => $section->academic_year_id, 'current_section_id' => $section->id]);
        ExamMark::factory()->for($school)->create(['exam_subject_id' => $examSubject->id, 'student_id' => $studentA->id, 'marks_obtained' => 80, 'is_absent' => false]);
        ExamMark::factory()->for($school)->create(['exam_subject_id' => $examSubject->id, 'student_id' => $studentB->id, 'marks_obtained' => 20, 'is_absent' => false]);

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/reports/academic-performance');

        $response->assertOk();
        $exams = $response->json('data.exams');
        $this->assertCount(1, $exams);
        $this->assertEquals(50, $exams[0]['average_percentage']);
        $this->assertEquals(50, $exams[0]['pass_rate']);
    }

    public function test_enrollment_report_counts_active_students_by_grade(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $section = $this->makeSection($school);
        Student::factory()->for($school)->count(2)->create([
            'academic_year_id' => $section->academic_year_id, 'current_grade_level_id' => $section->grade_level_id, 'current_section_id' => $section->id, 'status' => 'active',
        ]);

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/reports/enrollment');

        $response->assertOk()->assertJsonPath('data.active_total', 2);
    }

    public function test_operations_report_computes_library_overdue_count(): void
    {
        $school = $this->createSchool();
        $librarian = $this->createUserWithRole($school, 'Librarian');
        $book = Book::factory()->for($school)->create(['total_copies' => 2, 'available_copies' => 1]);
        $section = $this->makeSection($school);
        $student = Student::factory()->for($school)->create(['academic_year_id' => $section->academic_year_id, 'current_section_id' => $section->id]);
        BookIssue::factory()->for($school)->create([
            'book_id' => $book->id, 'student_id' => $student->id, 'user_id' => null,
            'status' => 'issued', 'due_date' => now()->subDays(2)->toDateString(), 'issued_by' => $librarian->id,
        ]);

        $response = $this->actingAsInSchool($librarian)->getJson('/api/v1/reports/operations');

        $response->assertOk()->assertJsonPath('data.library.currently_overdue', 1);
        $this->assertNull($response->json('data.transport'));
        $this->assertNull($response->json('data.hostel'));
    }

    private function makeSection(School $school): Section
    {
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();

        return Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
    }
}
