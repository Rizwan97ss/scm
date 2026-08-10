<?php

namespace Tests\Feature\Billing;

use App\Enums\SettingType;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Plan;
use App\Models\Section;
use App\Models\Student;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class PlanLimitEnforcementTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_student_admission_returns_402_at_the_plan_limit(): void
    {
        $plan = Plan::factory()->create(['max_students' => 1]);
        $school = $this->createSchool(['plan_id' => $plan->id]);
        app(SettingsService::class)->set('billing.max_students', 1, $school->id, SettingType::Integer, 'billing');
        Student::factory()->for($school)->create();

        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/students', [
            'first_name' => 'Overflow', 'last_name' => 'Student', 'gender' => 'male', 'date_of_birth' => '2018-01-15',
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
            'admission_date' => now()->toDateString(), 'guardians' => [],
        ]);

        $response->assertStatus(402);
    }

    public function test_staff_user_creation_returns_402_at_the_plan_limit(): void
    {
        $plan = Plan::factory()->create(['max_staff' => 1]);
        $school = $this->createSchool(['plan_id' => $plan->id]);
        app(SettingsService::class)->set('billing.max_staff', 1, $school->id, SettingType::Integer, 'billing');
        // The admin created below already fills the 1-seat limit.
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/users', [
            'first_name' => 'Overflow', 'last_name' => 'Teacher', 'email' => 'overflow@example.com',
            'password' => 'password', 'password_confirmation' => 'password', 'roles' => ['Teacher'],
        ]);

        $response->assertStatus(402);
    }

    public function test_creating_a_student_portal_user_never_counts_toward_the_staff_limit(): void
    {
        $plan = Plan::factory()->create(['max_staff' => 1]);
        $school = $this->createSchool(['plan_id' => $plan->id]);
        app(SettingsService::class)->set('billing.max_staff', 1, $school->id, SettingType::Integer, 'billing');
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/users', [
            'first_name' => 'A', 'last_name' => 'Student', 'email' => 'a.student@example.com',
            'password' => 'password', 'password_confirmation' => 'password', 'roles' => ['Student'],
        ]);

        $response->assertCreated();
    }
}
