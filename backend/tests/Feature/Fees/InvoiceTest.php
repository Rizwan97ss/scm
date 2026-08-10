<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeStudent(School $school): array
    {
        $year = AcademicYear::factory()->for($school)->create();
        $student = Student::factory()->for($school)->create(['academic_year_id' => $year->id]);

        return [$year, $student];
    }

    public function test_accountant_can_create_an_ad_hoc_invoice_with_line_items(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        [$year, $student] = $this->makeStudent($school);
        $category = FeeCategory::factory()->for($school)->create();

        $response = $this->actingAsInSchool($accountant)->postJson('/api/v1/invoices', [
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'items' => [
                ['fee_category_id' => $category->id, 'description' => 'Tuition — Term 1', 'unit_amount' => 5000],
                ['fee_category_id' => $category->id, 'description' => 'Lab fee', 'unit_amount' => 500, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.total', 6000);
        $response->assertJsonPath('data.status', 'issued');
        $this->assertDatabaseHas('invoices', ['school_id' => $school->id, 'student_id' => $student->id, 'total' => 6000]);
        $this->assertSame(2, $response->json('data.items') ? count($response->json('data.items')) : 0);
    }

    public function test_principal_cannot_create_an_invoice(): void
    {
        $school = $this->createSchool();
        $principal = $this->createUserWithRole($school, 'Principal');
        [$year, $student] = $this->makeStudent($school);
        $category = FeeCategory::factory()->for($school)->create();

        $response = $this->actingAsInSchool($principal)->postJson('/api/v1/invoices', [
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'items' => [['fee_category_id' => $category->id, 'description' => 'Tuition', 'unit_amount' => 1000]],
        ]);

        $response->assertStatus(403);
    }

    public function test_recording_a_payment_updates_balance_and_status(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        $invoice = Invoice::factory()->for($school)->create(['total' => 1000, 'subtotal' => 1000]);

        $response = $this->actingAsInSchool($accountant)->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $invoice->refresh();
        $this->assertSame(400.0, $invoice->amount_paid);
        $this->assertSame(600.0, $invoice->balance);
        $this->assertSame('partially_paid', $invoice->status->value);

        $this->actingAsInSchool($accountant)->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 600, 'method' => 'cash', 'paid_at' => now()->toDateString(),
        ])->assertCreated();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status->value);
        $this->assertSame(0.0, $invoice->balance);
    }

    public function test_payment_exceeding_balance_is_rejected(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        $invoice = Invoice::factory()->for($school)->create(['total' => 1000, 'subtotal' => 1000]);

        $response = $this->actingAsInSchool($accountant)->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 1500, 'method' => 'cash', 'paid_at' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0.0, $invoice->fresh()->amount_paid);
    }

    public function test_issuing_a_credit_note_reduces_balance_without_touching_amount_paid(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        $invoice = Invoice::factory()->for($school)->create(['total' => 1000, 'subtotal' => 1000]);

        $response = $this->actingAsInSchool($accountant)->postJson("/api/v1/invoices/{$invoice->id}/credit-notes", [
            'amount' => 300, 'reason' => 'Sibling discount applied late',
        ]);

        $response->assertCreated();
        $invoice->refresh();
        $this->assertSame(300.0, $invoice->credit_total);
        $this->assertSame(0.0, $invoice->amount_paid);
        $this->assertSame(700.0, $invoice->balance);
        $this->assertSame('partially_paid', $invoice->status->value);
    }

    public function test_voiding_an_invoice_with_payments_is_rejected(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        $invoice = Invoice::factory()->for($school)->create(['total' => 1000, 'subtotal' => 1000, 'amount_paid' => 200]);

        $response = $this->actingAsInSchool($accountant)->postJson("/api/v1/invoices/{$invoice->id}/void");

        $response->assertStatus(422);
        $this->assertSame('issued', $invoice->fresh()->status->value);
    }

    public function test_voiding_an_unpaid_invoice_succeeds(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        $invoice = Invoice::factory()->for($school)->create(['total' => 1000, 'subtotal' => 1000]);

        $response = $this->actingAsInSchool($accountant)->postJson("/api/v1/invoices/{$invoice->id}/void");

        $response->assertOk();
        $this->assertSame('void', $invoice->fresh()->status->value);
    }

    public function test_student_only_sees_their_own_invoices(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [, $student] = $this->makeStudent($school);
        $student->update(['user_id' => $studentUser->id]);

        Invoice::factory()->for($school)->create(['student_id' => $student->id]);
        [, $otherStudent] = $this->makeStudent($school);
        Invoice::factory()->for($school)->create(['student_id' => $otherStudent->id]);

        $response = $this->actingAsInSchool($studentUser)->getJson('/api/v1/invoices?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_parent_can_view_childs_fee_statement(): void
    {
        $school = $this->createSchool();
        $parentUser = $this->createUserWithRole($school, 'Parent');
        [, $student] = $this->makeStudent($school);
        $guardian = Guardian::factory()->for($school)->create(['user_id' => $parentUser->id]);
        $guardian->students()->attach($student->id, ['relationship_type' => 'mother', 'is_primary' => true]);

        Invoice::factory()->for($school)->create(['student_id' => $student->id, 'total' => 1200, 'subtotal' => 1200]);

        $response = $this->actingAsInSchool($parentUser)->getJson("/api/v1/parent/children/{$student->id}/invoices");

        $response->assertOk();
        $response->assertJsonPath('data.summary.total_billed', 1200);
    }

    public function test_accountant_cannot_view_another_schools_invoice(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $accountantA = $this->createUserWithRole($schoolA, 'Accountant');
        $invoiceB = Invoice::factory()->for($schoolB)->create();

        $response = $this->actingAsInSchool($accountantA)->getJson("/api/v1/invoices/{$invoiceB->id}");

        $response->assertStatus(404);
    }
}
