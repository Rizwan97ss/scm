<?php

namespace Tests\Feature\Transport;

use App\Models\AcademicYear;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentTransportAssignment;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class TransportTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_transport_staff_can_create_a_vehicle(): void
    {
        $school = $this->createSchool();
        $staff = $this->createUserWithRole($school, 'Transport Staff');

        $response = $this->actingAsInSchool($staff)->postJson('/api/v1/vehicles', [
            'registration_number' => 'AB-12-CD-3456', 'capacity' => 40,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('vehicles', ['registration_number' => 'AB-12-CD-3456']);
    }

    public function test_teacher_cannot_create_a_vehicle(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/vehicles', [
            'registration_number' => 'X', 'capacity' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_transport_staff_cannot_view_another_schools_vehicle_by_guessing_its_id(): void
    {
        $schoolA = $this->createSchool();
        $staffA = $this->createUserWithRole($schoolA, 'Transport Staff');

        $schoolB = $this->createSchool();
        tenancy()->initialize($schoolB);
        $vehicleB = Vehicle::factory()->create();

        $response = $this->actingAsInSchool($staffA)->getJson("/api/v1/vehicles/{$vehicleB->id}");

        $response->assertStatus(404);
    }

    public function test_creating_a_route_with_nested_stops_persists_all_stops_in_sequence(): void
    {
        $school = $this->createSchool();
        $staff = $this->createUserWithRole($school, 'Transport Staff');

        $response = $this->actingAsInSchool($staff)->postJson('/api/v1/routes', [
            'name' => 'North Loop',
            'stops' => [
                ['name' => 'Main Gate'],
                ['name' => 'Market Square'],
                ['name' => 'Hilltop'],
            ],
        ]);

        $response->assertCreated();
        $this->assertCount(3, $response->json('data.stops'));
        $this->assertDatabaseCount('route_stops', 3);
        $this->assertDatabaseHas('route_stops', ['name' => 'Hilltop', 'sequence' => 3]);
    }

    public function test_assigning_a_student_to_transport_deactivates_their_previous_assignment(): void
    {
        $school = $this->createSchool();
        $staff = $this->createUserWithRole($school, 'Transport Staff');
        $student = $this->makeStudent($school);
        tenancy()->initialize($school);
        $route = Route::factory()->create();
        tenancy()->initialize($school);
        $stop = RouteStop::factory()->create(['route_id' => $route->id]);
        tenancy()->initialize($school);
        $vehicle = Vehicle::factory()->create();

        tenancy()->initialize($school);
        $first = StudentTransportAssignment::factory()->create([
            'student_id' => $student->id, 'route_id' => $route->id, 'route_stop_id' => $stop->id,
            'vehicle_id' => $vehicle->id, 'is_active' => true,
        ]);

        $response = $this->actingAsInSchool($staff)->postJson('/api/v1/student-transport-assignments', [
            'student_id' => $student->id, 'route_id' => $route->id, 'route_stop_id' => $stop->id,
            'vehicle_id' => $vehicle->id, 'effective_from' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertFalse($first->fresh()->is_active);
        $this->assertDatabaseHas('student_transport_assignments', ['id' => $response->json('data.id'), 'is_active' => true]);
    }

    private function makeStudent(School $school): Student
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();

        tenancy()->initialize($school);
        return Student::factory()->create(['academic_year_id' => $year->id]);
    }
}
