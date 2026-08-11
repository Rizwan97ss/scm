<?php

namespace Tests\Feature\Hostel;

use App\Models\AcademicYear;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class HostelTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_hr_staff_can_create_a_hostel_and_a_room(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');

        $hostel = $this->actingAsInSchool($hr)->postJson('/api/v1/hostels', [
            'name' => 'Sunrise Hostel', 'type' => 'boys',
        ]);
        $hostel->assertCreated();

        $room = $this->actingAsInSchool($hr)->postJson('/api/v1/hostel-rooms', [
            'hostel_id' => $hostel->json('data.id'), 'room_number' => '101', 'capacity' => 2,
        ]);
        $room->assertCreated();
        $this->assertDatabaseHas('hostel_rooms', ['room_number' => '101']);
    }

    public function test_teacher_cannot_create_a_hostel(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/hostels', ['name' => 'X', 'type' => 'boys']);

        $response->assertStatus(403);
    }

    public function test_allocating_a_student_fills_the_room_and_a_second_allocation_supersedes_the_first(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        tenancy()->initialize($school);
        $hostel = Hostel::factory()->create();
        tenancy()->initialize($school);
        $room = HostelRoom::factory()->create(['hostel_id' => $hostel->id, 'capacity' => 2]);
        $studentA = $this->makeStudent($school);
        $studentB = $this->makeStudent($school);

        $first = $this->actingAsInSchool($hr)->postJson('/api/v1/hostel-allocations', [
            'student_id' => $studentA->id, 'hostel_room_id' => $room->id, 'allocated_date' => now()->toDateString(),
        ]);
        $first->assertCreated();

        $reAllocate = $this->actingAsInSchool($hr)->postJson('/api/v1/hostel-allocations', [
            'student_id' => $studentA->id, 'hostel_room_id' => $room->id, 'allocated_date' => now()->addDay()->toDateString(),
        ]);
        $reAllocate->assertCreated();

        $this->assertDatabaseHas('hostel_allocations', ['id' => $first->json('data.id'), 'status' => 'vacated']);
        $this->assertDatabaseHas('hostel_allocations', ['id' => $reAllocate->json('data.id'), 'status' => 'allocated']);

        $this->actingAsInSchool($hr)->postJson('/api/v1/hostel-allocations', [
            'student_id' => $studentB->id, 'hostel_room_id' => $room->id, 'allocated_date' => now()->toDateString(),
        ])->assertCreated();
    }

    public function test_cannot_allocate_a_student_to_a_room_that_is_already_at_full_capacity(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        tenancy()->initialize($school);
        $room = HostelRoom::factory()->create(['capacity' => 1]);
        tenancy()->initialize($school);
        HostelAllocation::factory()->create(['hostel_room_id' => $room->id, 'student_id' => $this->makeStudent($school)->id, 'status' => 'allocated']);
        $studentB = $this->makeStudent($school);

        $response = $this->actingAsInSchool($hr)->postJson('/api/v1/hostel-allocations', [
            'student_id' => $studentB->id, 'hostel_room_id' => $room->id, 'allocated_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_vacating_an_allocation_marks_it_vacated_and_cannot_be_vacated_twice(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        tenancy()->initialize($school);
        $hostel = Hostel::factory()->create();
        tenancy()->initialize($school);
        $room = HostelRoom::factory()->create(['hostel_id' => $hostel->id]);
        tenancy()->initialize($school);
        $allocation = HostelAllocation::factory()->create(['hostel_room_id' => $room->id, 'status' => 'allocated']);

        $response = $this->actingAsInSchool($hr)->postJson("/api/v1/hostel-allocations/{$allocation->id}/vacate");
        $response->assertOk()->assertJsonPath('data.status', 'vacated');

        $again = $this->actingAsInSchool($hr)->postJson("/api/v1/hostel-allocations/{$allocation->id}/vacate");
        $again->assertStatus(422);
    }

    private function makeStudent(School $school): Student
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();

        tenancy()->initialize($school);
        return Student::factory()->create(['academic_year_id' => $year->id]);
    }
}
