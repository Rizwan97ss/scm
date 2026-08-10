<?php

namespace Tests\Unit\Services;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Plan;
use App\Models\Student;
use App\Services\PlanLimitService;
use App\Services\SchoolProvisioningService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assert_can_add_student_throws_once_at_the_limit(): void
    {
        $this->seed(PermissionSeeder::class);
        $plan = Plan::factory()->create(['max_students' => 1, 'max_staff' => null]);
        $school = app(SchoolProvisioningService::class)->provision(
            ['name' => 'Limit School', 'short_name' => 'limit-school'],
            ['first_name' => 'A', 'last_name' => 'B', 'email' => 'admin@limit-school.test', 'password' => 'password'],
            $plan
        );

        $service = app(PlanLimitService::class);

        // No students yet — under the limit of 1.
        $service->assertCanAddStudent($school);

        Student::factory()->for($school)->create();

        $this->expectException(PlanLimitExceededException::class);
        $service->assertCanAddStudent($school);
    }

    public function test_assert_can_add_staff_user_ignores_student_and_parent_roles(): void
    {
        $this->seed(PermissionSeeder::class);
        $plan = Plan::factory()->create(['max_staff' => 1]);
        $school = app(SchoolProvisioningService::class)->provision(
            ['name' => 'Staff Limit School', 'short_name' => 'staff-limit-school'],
            ['first_name' => 'A', 'last_name' => 'B', 'email' => 'admin@staff-limit-school.test', 'password' => 'password'],
            $plan
        );

        // The provisioned admin already fills the 1-seat staff limit.
        $this->expectException(PlanLimitExceededException::class);
        app(PlanLimitService::class)->assertCanAddStaffUser($school);
    }

    public function test_null_limit_means_unlimited(): void
    {
        $this->seed(PermissionSeeder::class);
        $plan = Plan::factory()->create(['max_students' => null]);
        $school = app(SchoolProvisioningService::class)->provision(
            ['name' => 'Unlimited School', 'short_name' => 'unlimited-school'],
            ['first_name' => 'A', 'last_name' => 'B', 'email' => 'admin@unlimited-school.test', 'password' => 'password'],
            $plan
        );

        Student::factory()->for($school)->count(50)->create();

        // Does not throw, however many students already exist.
        app(PlanLimitService::class)->assertCanAddStudent($school);
        $this->assertTrue(true);
    }
}
