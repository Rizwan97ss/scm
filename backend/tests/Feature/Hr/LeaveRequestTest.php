<?php

namespace Tests\Feature\Hr;

use App\Models\LeaveType;
use App\Models\StaffAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_teacher_can_apply_for_their_own_leave(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        $leaveType = LeaveType::factory()->create();

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'reason' => 'Family event',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.days', 3);
        $response->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('leave_requests', ['user_id' => $teacher->id, 'status' => 'pending']);
    }

    public function test_student_cannot_apply_for_leave(): void
    {
        $school = $this->createSchool();
        $student = $this->createUserWithRole($school, 'Student');
        tenancy()->initialize($school);
        $leaveType = LeaveType::factory()->create();

        $response = $this->actingAsInSchool($student)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'reason' => 'n/a',
        ]);

        $response->assertStatus(403);
    }

    public function test_staff_member_only_sees_their_own_leave_requests_without_leave_view(): void
    {
        $school = $this->createSchool();
        $teacherA = $this->createUserWithRole($school, 'Teacher');
        $teacherB = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        $leaveType = LeaveType::factory()->create();

        $this->actingAsInSchool($teacherA)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'A',
        ])->assertCreated();
        $this->actingAsInSchool($teacherB)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'B',
        ])->assertCreated();

        $response = $this->actingAsInSchool($teacherA)->getJson('/api/v1/leave-requests?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_hr_staff_sees_every_leave_request(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $hr = $this->createUserWithRole($school, 'HR Staff');
        tenancy()->initialize($school);
        $leaveType = LeaveType::factory()->create();

        $this->actingAsInSchool($teacher)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'A',
        ])->assertCreated();

        $response = $this->actingAsInSchool($hr)->getJson('/api/v1/leave-requests?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_approving_a_leave_request_marks_staff_attendance_as_on_leave_for_each_day(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $hr = $this->createUserWithRole($school, 'HR Staff');
        tenancy()->initialize($school);
        $leaveType = LeaveType::factory()->create();

        $start = now()->addWeek()->startOfWeek();
        $end = (clone $start)->addDays(1);

        $create = $this->actingAsInSchool($teacher)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => 'Trip',
        ]);
        $leaveRequestId = $create->json('data.id');

        $response = $this->actingAsInSchool($hr)->postJson("/api/v1/leave-requests/{$leaveRequestId}/review", [
            'status' => 'approved',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame(2, StaffAttendance::query()->where('user_id', $teacher->id)->where('status', 'on_leave')->count());
    }

    public function test_teacher_cannot_approve_their_own_leave_request(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        $leaveType = LeaveType::factory()->create();

        $create = $this->actingAsInSchool($teacher)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'A',
        ]);
        $leaveRequestId = $create->json('data.id');

        $response = $this->actingAsInSchool($teacher)->postJson("/api/v1/leave-requests/{$leaveRequestId}/review", [
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
    }

    public function test_staff_member_can_cancel_their_own_pending_request_but_not_after_review(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $hr = $this->createUserWithRole($school, 'HR Staff');
        tenancy()->initialize($school);
        $leaveType = LeaveType::factory()->create();

        $create = $this->actingAsInSchool($teacher)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'A',
        ]);
        $leaveRequestId = $create->json('data.id');

        $this->actingAsInSchool($hr)->postJson("/api/v1/leave-requests/{$leaveRequestId}/review", ['status' => 'approved'])->assertOk();

        $response = $this->actingAsInSchool($teacher)->postJson("/api/v1/leave-requests/{$leaveRequestId}/cancel");

        $response->assertStatus(403);
    }
}
