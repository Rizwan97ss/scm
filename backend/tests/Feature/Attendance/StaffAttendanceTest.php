<?php

namespace Tests\Feature\Attendance;

use App\Models\StaffAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class StaffAttendanceTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_hr_staff_can_mark_bulk_attendance_for_other_staff(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($hr)->postJson('/api/v1/attendance/staff', [
            'date' => '2026-08-10',
            'entries' => [['user_id' => $teacher->id, 'status' => 'present']],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('staff_attendances', ['user_id' => $teacher->id, 'status' => 'present']);
    }

    public function test_teacher_cannot_mark_staff_attendance(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $otherTeacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/attendance/staff', [
            'date' => '2026-08-10',
            'entries' => [['user_id' => $otherTeacher->id, 'status' => 'present']],
        ]);

        $response->assertStatus(403);
    }

    public function test_staff_member_can_check_in_and_check_out(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $checkIn = $this->actingAsInSchool($teacher)->postJson('/api/v1/attendance/staff/check-in');
        $checkIn->assertOk();
        $this->assertNotNull($checkIn->json('data.check_in_time'));

        $checkOut = $this->actingAsInSchool($teacher)->postJson('/api/v1/attendance/staff/check-out');
        $checkOut->assertOk();
        $this->assertNotNull($checkOut->json('data.check_out_time'));

        $this->assertSame(1, StaffAttendance::query()->where('user_id', $teacher->id)->count());
    }

    public function test_checking_out_without_checking_in_returns_a_clear_error(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/attendance/staff/check-out');

        $response->assertStatus(422);
    }

    public function test_staff_member_can_always_view_their_own_attendance(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $admin = $this->createUserWithRole($school, 'School Admin');

        tenancy()->initialize($school);
        StaffAttendance::factory()->create([
            'user_id' => $teacher->id, 'marked_by' => $admin->id, 'date' => '2026-08-10', 'status' => 'present',
        ]);

        $response = $this->actingAsInSchool($teacher)->getJson('/api/v1/attendance/staff');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_staff_member_cannot_see_other_staffs_attendance_without_permission(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $otherTeacher = $this->createUserWithRole($school, 'Teacher');
        $admin = $this->createUserWithRole($school, 'School Admin');

        tenancy()->initialize($school);
        StaffAttendance::factory()->create([
            'user_id' => $otherTeacher->id, 'marked_by' => $admin->id, 'date' => '2026-08-10',
        ]);

        $response = $this->actingAsInSchool($teacher)->getJson('/api/v1/attendance/staff');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Regression test: the `date` column's stored value includes a time
     * component on SQLite (see AttendanceService's docblock), so a naive
     * exact-match filter[date] query silently returns zero rows even when a
     * same-day record exists. This exact bug shipped once — caught only by
     * manually checking the Staff Attendance page in a browser, where the
     * "checked in today" card kept saying "not checked in" right after a
     * successful check-in — because no test exercised filter[date] before.
     */
    public function test_can_filter_staff_attendance_list_by_exact_date(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $teacher = $this->createUserWithRole($school, 'Teacher');

        tenancy()->initialize($school);
        StaffAttendance::factory()->create(['user_id' => $teacher->id, 'marked_by' => $admin->id, 'date' => '2026-08-10']);
        tenancy()->initialize($school);
        StaffAttendance::factory()->create(['user_id' => $teacher->id, 'marked_by' => $admin->id, 'date' => '2026-08-11']);

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/attendance/staff?filter[date]=2026-08-10');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('2026-08-10', $response->json('data.0.date'));
    }

    public function test_school_admin_can_view_and_correct_any_staff_attendance(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $teacher = $this->createUserWithRole($school, 'Teacher');

        tenancy()->initialize($school);
        $record = StaffAttendance::factory()->create([
            'user_id' => $teacher->id, 'marked_by' => $admin->id, 'date' => '2026-08-10', 'status' => 'absent',
        ]);

        $listResponse = $this->actingAsInSchool($admin)->getJson('/api/v1/attendance/staff');
        $listResponse->assertOk();
        $this->assertCount(1, $listResponse->json('data'));

        $correctResponse = $this->actingAsInSchool($admin)->putJson("/api/v1/attendance/staff/{$record->id}", ['status' => 'present']);
        $correctResponse->assertOk()->assertJsonPath('data.status', 'present');
    }
}
