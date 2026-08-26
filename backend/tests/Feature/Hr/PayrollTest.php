<?php

namespace Tests\Feature\Hr;

use App\Models\Payslip;
use App\Models\SalaryStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_hr_can_create_a_salary_structure_and_a_new_one_closes_the_previous(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $first = $this->actingAsInSchool($hr)->postJson('/api/v1/salary-structures', [
            'user_id' => $teacher->id, 'basic_salary' => 3000, 'effective_from' => now()->subMonth()->toDateString(),
        ]);
        $first->assertCreated()->assertJsonPath('data.is_active', true);

        $second = $this->actingAsInSchool($hr)->postJson('/api/v1/salary-structures', [
            'user_id' => $teacher->id, 'basic_salary' => 3500, 'effective_from' => now()->toDateString(),
        ]);
        $second->assertCreated();

        $this->assertSame(1, SalaryStructure::query()->where('user_id', $teacher->id)->where('is_active', true)->count());
        $this->assertDatabaseHas('salary_structures', ['id' => $first->json('data.id'), 'is_active' => false]);
    }

    public function test_bulk_payroll_generation_creates_one_payslip_per_active_structure_and_is_idempotent(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        $teacherA = $this->createUserWithRole($school, 'Teacher');
        $teacherB = $this->createUserWithRole($school, 'Teacher');

        tenancy()->initialize($school);
        SalaryStructure::factory()->create(['user_id' => $teacherA->id, 'basic_salary' => 3000, 'allowances' => 200, 'deductions' => 50]);
        tenancy()->initialize($school);
        SalaryStructure::factory()->create(['user_id' => $teacherB->id, 'basic_salary' => 4000, 'allowances' => 0, 'deductions' => 0]);

        $payload = ['month' => now()->month, 'year' => now()->year];

        $first = $this->actingAsInSchool($hr)->postJson('/api/v1/payslips/generate', $payload);
        $first->assertOk()->assertJsonPath('data.created_count', 2);

        $this->assertDatabaseHas('payslips', ['user_id' => $teacherA->id, 'net_salary' => 3150]);

        $second = $this->actingAsInSchool($hr)->postJson('/api/v1/payslips/generate', $payload);
        $second->assertOk()->assertJsonPath('data.created_count', 0)->assertJsonPath('data.skipped_count', 2);

        $this->assertSame(2, Payslip::query()->count());
    }

    public function test_staff_member_sees_only_their_own_payslips(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        $teacherA = $this->createUserWithRole($school, 'Teacher');
        $teacherB = $this->createUserWithRole($school, 'Teacher');

        tenancy()->initialize($school);
        Payslip::factory()->create(['user_id' => $teacherA->id, 'generated_by' => $hr->id]);
        tenancy()->initialize($school);
        Payslip::factory()->create(['user_id' => $teacherB->id, 'generated_by' => $hr->id]);

        $response = $this->actingAsInSchool($teacherA)->getJson('/api/v1/payslips?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_principal_cannot_view_payroll(): void
    {
        $school = $this->createSchool();
        $principal = $this->createUserWithRole($school, 'Principal');

        $response = $this->actingAsInSchool($principal)->getJson('/api/v1/salary-structures');

        $response->assertStatus(403);
    }

    public function test_marking_a_payslip_paid_twice_is_rejected(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        tenancy()->initialize($school);
        $payslip = Payslip::factory()->create(['generated_by' => $hr->id]);

        $this->actingAsInSchool($hr)->postJson("/api/v1/payslips/{$payslip->id}/mark-paid")->assertOk()->assertJsonPath('data.status', 'paid');

        $response = $this->actingAsInSchool($hr)->postJson("/api/v1/payslips/{$payslip->id}/mark-paid");

        $response->assertStatus(422);
    }

    public function test_hr_staff_cannot_view_another_schools_payslip_receipt_by_guessing_its_id(): void
    {
        $schoolA = $this->createSchool();
        $hrA = $this->createUserWithRole($schoolA, 'HR Staff');

        $schoolB = $this->createSchool();
        $hrB = $this->createUserWithRole($schoolB, 'HR Staff');
        tenancy()->initialize($schoolB);
        $payslipB = Payslip::factory()->create(['generated_by' => $hrB->id]);

        $response = $this->actingAsInSchool($hrA)->getJson("/api/v1/payslips/{$payslipB->id}/receipt");

        $response->assertStatus(404);
    }

    public function test_hr_staff_cannot_mark_another_schools_payslip_paid(): void
    {
        $schoolA = $this->createSchool();
        $hrA = $this->createUserWithRole($schoolA, 'HR Staff');

        $schoolB = $this->createSchool();
        $hrB = $this->createUserWithRole($schoolB, 'HR Staff');
        tenancy()->initialize($schoolB);
        $payslipB = Payslip::factory()->create(['generated_by' => $hrB->id]);

        $response = $this->actingAsInSchool($hrA)->postJson("/api/v1/payslips/{$payslipB->id}/mark-paid");

        $response->assertStatus(404);
        tenancy()->initialize($schoolB);
        $this->assertNotSame('paid', $payslipB->fresh()->status);
    }

    public function test_staff_member_cannot_view_another_schools_salary_structure_by_guessing_its_id(): void
    {
        $schoolA = $this->createSchool();
        $teacherA = $this->createUserWithRole($schoolA, 'Teacher');

        $schoolB = $this->createSchool();
        $teacherB = $this->createUserWithRole($schoolB, 'Teacher');
        tenancy()->initialize($schoolB);
        $structureB = SalaryStructure::factory()->create(['user_id' => $teacherB->id]);

        $response = $this->actingAsInSchool($teacherA)->getJson("/api/v1/salary-structures/{$structureB->id}");

        $response->assertStatus(404);
    }
}
