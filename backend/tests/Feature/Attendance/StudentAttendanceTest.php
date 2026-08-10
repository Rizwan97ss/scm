<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class StudentAttendanceTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeSectionWithStudents(School $school, int $count = 3, ?int $classTeacherId = null): array
    {
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create([
            'academic_year_id' => $year->id,
            'grade_level_id' => $gradeLevel->id,
            'class_teacher_id' => $classTeacherId,
        ]);

        $students = Student::factory()->for($school)->count($count)->create([
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
        ]);

        return [$section, $students];
    }

    public function test_school_admin_can_mark_bulk_attendance_for_a_section(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$section, $students] = $this->makeSectionWithStudents($school, 3);

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/attendance/students', [
            'section_id' => $section->id,
            'date' => '2026-08-10',
            'entries' => [
                ['student_id' => $students[0]->id, 'status' => 'present'],
                ['student_id' => $students[1]->id, 'status' => 'absent', 'remarks' => 'Sick'],
                ['student_id' => $students[2]->id, 'status' => 'late'],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $students[1]->id,
            'status' => 'absent',
            'remarks' => 'Sick',
        ]);
    }

    public function test_marking_the_same_student_and_date_twice_updates_instead_of_duplicating(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$section, $students] = $this->makeSectionWithStudents($school, 1);

        $mark = fn (string $status) => $this->actingAsInSchool($admin)->postJson('/api/v1/attendance/students', [
            'section_id' => $section->id,
            'date' => '2026-08-10',
            'entries' => [['student_id' => $students[0]->id, 'status' => $status]],
        ]);

        $mark('present')->assertOk();
        $mark('absent')->assertOk();

        $this->assertSame(1, StudentAttendance::query()->where('student_id', $students[0]->id)->count());
        $this->assertDatabaseHas('student_attendances', ['student_id' => $students[0]->id, 'status' => 'absent']);
    }

    public function test_teacher_cannot_mark_attendance_for_a_section_they_do_not_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$otherSection, $students] = $this->makeSectionWithStudents($school, 1);

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/attendance/students', [
            'section_id' => $otherSection->id,
            'date' => '2026-08-10',
            'entries' => [['student_id' => $students[0]->id, 'status' => 'present']],
        ]);

        $response->assertStatus(403);
    }

    public function test_class_teacher_can_mark_and_correct_attendance_for_their_own_section(): void
    {
        $school = $this->createSchool();
        $classTeacher = $this->createUserWithRole($school, 'Class Teacher');
        [$section, $students] = $this->makeSectionWithStudents($school, 1, $classTeacher->id);

        $markResponse = $this->actingAsInSchool($classTeacher)->postJson('/api/v1/attendance/students', [
            'section_id' => $section->id,
            'date' => '2026-08-10',
            'entries' => [['student_id' => $students[0]->id, 'status' => 'absent']],
        ]);
        $markResponse->assertOk();
        $attendanceId = $markResponse->json('data.0.id');

        $correctResponse = $this->actingAsInSchool($classTeacher)->putJson("/api/v1/attendance/students/{$attendanceId}", [
            'status' => 'present',
            'remarks' => 'Arrived late, marked present after correction',
        ]);

        $correctResponse->assertOk()->assertJsonPath('data.status', 'present');
    }

    /**
     * @see StaffAttendanceTest::test_can_filter_staff_attendance_list_by_exact_date()
     * for why filter[date] can't be a naive exact-string match.
     */
    public function test_can_filter_student_attendance_list_by_exact_date(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$section, $students] = $this->makeSectionWithStudents($school, 1);

        StudentAttendance::factory()->for($school)->create([
            'student_id' => $students[0]->id, 'section_id' => $section->id,
            'academic_year_id' => $section->academic_year_id, 'marked_by' => $admin->id, 'date' => '2026-08-10',
        ]);
        StudentAttendance::factory()->for($school)->create([
            'student_id' => $students[0]->id, 'section_id' => $section->id,
            'academic_year_id' => $section->academic_year_id, 'marked_by' => $admin->id, 'date' => '2026-08-11',
        ]);

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/attendance/students?filter[date]=2026-08-10');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('2026-08-10', $response->json('data.0.date'));
    }

    public function test_teacher_can_only_see_attendance_for_their_assigned_sections(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $otherTeacher = $this->createUserWithRole($school, 'Teacher');
        [$mySection, $myStudents] = $this->makeSectionWithStudents($school, 1, $teacher->id);
        [$otherSection, $otherStudents] = $this->makeSectionWithStudents($school, 1, $otherTeacher->id);

        StudentAttendance::factory()->for($school)->create([
            'student_id' => $myStudents[0]->id, 'section_id' => $mySection->id,
            'academic_year_id' => $mySection->academic_year_id, 'marked_by' => $teacher->id, 'date' => '2026-08-10',
        ]);
        StudentAttendance::factory()->for($school)->create([
            'student_id' => $otherStudents[0]->id, 'section_id' => $otherSection->id,
            'academic_year_id' => $otherSection->academic_year_id, 'marked_by' => $otherTeacher->id, 'date' => '2026-08-10',
        ]);

        $response = $this->actingAsInSchool($teacher)->getJson('/api/v1/attendance/students?per_page=50');

        $response->assertOk();
        $sectionIds = collect($response->json('data'))->pluck('section_id');
        $this->assertTrue($sectionIds->contains($mySection->id));
        $this->assertFalse($sectionIds->contains($otherSection->id));
    }

    public function test_student_can_view_their_own_attendance_summary(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$section, $students] = $this->makeSectionWithStudents($school, 1);
        $student = $students[0];
        $student->update(['user_id' => $studentUser->id]);

        StudentAttendance::factory()->for($school)->create([
            'student_id' => $student->id, 'section_id' => $section->id, 'academic_year_id' => $section->academic_year_id,
            'marked_by' => $studentUser->id, 'date' => now()->toDateString(), 'status' => 'present',
        ]);

        $response = $this->actingAsInSchool($studentUser)->getJson("/api/v1/attendance/students/summary?student_id={$student->id}");

        $response->assertOk()->assertJsonPath('data.total_marked', 1);
    }

    public function test_student_cannot_view_another_students_attendance_summary(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$section, $students] = $this->makeSectionWithStudents($school, 2);
        $students[0]->update(['user_id' => $studentUser->id]);

        $response = $this->actingAsInSchool($studentUser)->getJson("/api/v1/attendance/students/summary?student_id={$students[1]->id}");

        $response->assertStatus(403);
    }

    public function test_parent_can_view_their_childs_attendance(): void
    {
        $school = $this->createSchool();
        $parentUser = $this->createUserWithRole($school, 'Parent');
        $guardian = Guardian::factory()->for($school)->create(['user_id' => $parentUser->id]);
        [$section, $students] = $this->makeSectionWithStudents($school, 1);
        $child = $students[0];
        $guardian->students()->attach($child->id, ['relationship_type' => 'mother', 'is_primary' => true]);

        StudentAttendance::factory()->for($school)->create([
            'student_id' => $child->id, 'section_id' => $section->id, 'academic_year_id' => $section->academic_year_id,
            'marked_by' => $parentUser->id, 'date' => now()->toDateString(), 'status' => 'present',
        ]);

        $response = $this->actingAsInSchool($parentUser)->getJson("/api/v1/parent/children/{$child->id}/attendance");

        // Both the summary AND the raw records list must agree — a prior bug
        // had childAttendance()'s records query use whereBetween('date', [...])
        // instead of whereDate(), so a same-day record satisfied the summary
        // (built via AttendanceService, already whereDate()-safe) but silently
        // vanished from the records array shown underneath it.
        $response->assertOk()
            ->assertJsonPath('data.summary.total_marked', 1)
            ->assertJsonCount(1, 'data.records');
    }

    public function test_attendance_percentage_is_calculated_with_half_day_weighting(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$section, $students] = $this->makeSectionWithStudents($school, 1);
        $student = $students[0];

        foreach ([['2026-08-01', 'present'], ['2026-08-02', 'present'], ['2026-08-03', 'absent'], ['2026-08-04', 'half_day']] as [$date, $status]) {
            StudentAttendance::factory()->for($school)->create([
                'student_id' => $student->id, 'section_id' => $section->id, 'academic_year_id' => $section->academic_year_id,
                'marked_by' => $admin->id, 'date' => $date, 'status' => $status,
            ]);
        }

        $response = $this->actingAsInSchool($admin)->getJson("/api/v1/attendance/students/summary?student_id={$student->id}&from=2026-08-01&to=2026-08-04");

        $response->assertOk()->assertJsonPath('data.percentage', 62.5);
    }

    public function test_school_admin_cannot_correct_another_schools_attendance_record(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $adminA = $this->createUserWithRole($schoolA, 'School Admin');
        [$sectionB, $studentsB] = $this->makeSectionWithStudents($schoolB, 1);

        $recordB = StudentAttendance::factory()->for($schoolB)->create([
            'student_id' => $studentsB[0]->id, 'section_id' => $sectionB->id, 'academic_year_id' => $sectionB->academic_year_id,
            'marked_by' => $studentsB[0]->id, 'date' => '2026-08-10',
        ]);

        $response = $this->actingAsInSchool($adminA)->putJson("/api/v1/attendance/students/{$recordB->id}", ['status' => 'present']);

        $response->assertStatus(404);
    }
}
