<?php

namespace Tests\Feature\FrontDesk;

use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class VisitorTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_receptionist_can_log_a_visitor_check_in(): void
    {
        $school = $this->createSchool();
        $receptionist = $this->createUserWithRole($school, 'Receptionist');

        $response = $this->actingAsInSchool($receptionist)->postJson('/api/v1/visitors', [
            'name' => 'Jane Doe', 'purpose' => 'Admission enquiry', 'whom_to_meet' => 'Principal',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Jane Doe');
        $this->assertNotNull($response->json('data.check_in_time'));
        $this->assertNull($response->json('data.check_out_time'));
    }

    public function test_teacher_cannot_log_a_visitor(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/visitors', [
            'name' => 'X', 'purpose' => 'Y',
        ]);

        $response->assertStatus(403);
    }

    public function test_checking_out_a_visitor_stamps_the_checkout_time_and_cannot_be_repeated(): void
    {
        $school = $this->createSchool();
        $receptionist = $this->createUserWithRole($school, 'Receptionist');
        $visitor = Visitor::factory()->for($school)->create(['check_out_time' => null]);

        $response = $this->actingAsInSchool($receptionist)->postJson("/api/v1/visitors/{$visitor->id}/check-out");
        $response->assertOk();
        $this->assertNotNull($response->json('data.check_out_time'));

        $again = $this->actingAsInSchool($receptionist)->postJson("/api/v1/visitors/{$visitor->id}/check-out");
        $again->assertStatus(422);
    }

    public function test_visitors_can_be_filtered_by_status(): void
    {
        $school = $this->createSchool();
        $receptionist = $this->createUserWithRole($school, 'Receptionist');
        Visitor::factory()->for($school)->create(['check_out_time' => null]);
        Visitor::factory()->for($school)->create(['check_out_time' => now()]);

        $response = $this->actingAsInSchool($receptionist)->getJson('/api/v1/visitors?filter[status]=checked_in');

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
