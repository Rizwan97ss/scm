<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\CreditNote;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns everything that changes an Invoice's money state: creating one
 * (ad-hoc or bulk-generated from a FeeStructure), recording a payment
 * against it (delegating the actual "how was this collected" bit to
 * PaymentGatewayInterface), issuing a credit note, voiding it, and
 * recomputing amount_paid/credit_total/status afterward. Controllers never
 * touch these columns directly — see InvoiceController.
 */
class InvoiceService
{
    public function __construct(
        private readonly FeeNumberService $feeNumbers,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * @param  array{student_id: int, academic_year_id: int, issue_date: string, due_date: string, notes: ?string, items: array<int, array{fee_category_id: int, fee_structure_id: ?int, description: string, quantity?: int, unit_amount: float}>}  $data
     */
    public function createInvoice(array $data, User $creator): Invoice
    {
        return DB::transaction(function () use ($data, $creator) {
            $school = $creator->school;

            $subtotal = collect($data['items'])->sum(fn ($item) => ($item['quantity'] ?? 1) * $item['unit_amount']);

            $invoice = Invoice::query()->create([
                'student_id' => $data['student_id'],
                'academic_year_id' => $data['academic_year_id'],
                'invoice_number' => $this->feeNumbers->nextInvoiceNumber($school),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'status' => InvoiceStatus::Issued,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'total' => $subtotal,
                'notes' => $data['notes'] ?? null,
                'created_by' => $creator->id,
            ]);

            foreach ($data['items'] as $item) {
                $quantity = $item['quantity'] ?? 1;
                $invoice->items()->create([
                    'fee_category_id' => $item['fee_category_id'],
                    'fee_structure_id' => $item['fee_structure_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_amount' => $item['unit_amount'],
                    'amount' => $quantity * $item['unit_amount'],
                ]);
            }

            return $invoice->load(['items', 'student']);
        });
    }

    /**
     * Bulk-generates one invoice per matching student for a FeeStructure.
     * Idempotent: a student who already has a non-void invoice carrying an
     * item for this exact fee structure is skipped rather than double-billed.
     * Applies that student's StudentFeeAssignment discount if one exists.
     *
     * Deliberately one transaction per student (generateOneInvoice()), not
     * one transaction around the whole loop. Besides being better bulk-op
     * semantics (one student's failure doesn't roll back invoices already
     * correctly generated for earlier students), a single long transaction
     * doing 3+ sequential writes reproduced a real, deterministic "unable to
     * open database file" (SQLite error 14) under `php artisan serve` on
     * Windows — confirmed via Tinker (same code, no HTTP/middleware layer)
     * succeeding every time, while the identical logic run through an actual
     * request failed on the second write, every time, regardless of
     * busy_timeout/WAL settings. Splitting into short-lived per-item
     * transactions avoided it entirely. Root cause not fully pinned down
     * (looks like a php-cli-server + PDO SQLite interaction on this
     * platform), but the fix is sound in its own right, not just a
     * workaround.
     *
     * @return array{created: Collection<int, Invoice>, skipped_count: int}
     */
    public function generateFromStructure(FeeStructure $structure, ?int $sectionId, string $issueDate, string $dueDate, User $creator): array
    {
        $students = Student::query()
            ->whereIn('status', StudentStatus::activeStatuses())
            ->when($sectionId, fn ($q) => $q->where('current_section_id', $sectionId))
            ->when(! $sectionId && $structure->grade_level_id, fn ($q) => $q->where('current_grade_level_id', $structure->grade_level_id))
            ->get();

        $alreadyInvoicedStudentIds = Student::query()
            ->whereHas('invoices', fn ($q) => $q->where('status', '!=', InvoiceStatus::Void)
                ->whereHas('items', fn ($qi) => $qi->where('fee_structure_id', $structure->id)))
            ->pluck('id')
            ->all();

        $created = collect();
        $skippedCount = 0;

        // Each student's invoice is its own transaction rather than one
        // transaction wrapping the whole batch — a school with many students
        // shouldn't have an unrelated failure partway through roll back
        // invoices that were already correctly generated for earlier students.
        foreach ($students as $student) {
            if (in_array($student->id, $alreadyInvoicedStudentIds, true)) {
                $skippedCount++;

                continue;
            }

            $created->push($this->generateOneInvoice($structure, $student, $issueDate, $dueDate, $creator));
        }

        return ['created' => $created, 'skipped_count' => $skippedCount];
    }

    private function generateOneInvoice(FeeStructure $structure, Student $student, string $issueDate, string $dueDate, User $creator): Invoice
    {
        return DB::transaction(function () use ($structure, $student, $issueDate, $dueDate, $creator) {
            $assignment = StudentFeeAssignment::query()
                ->where('student_id', $student->id)
                ->where('fee_structure_id', $structure->id)
                ->first();

            $baseAmount = (float) $structure->amount;
            $effectiveAmount = $assignment ? $assignment->applyDiscount($baseAmount) : $baseAmount;

            $invoice = Invoice::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $structure->academic_year_id,
                'invoice_number' => $this->feeNumbers->nextInvoiceNumber($structure->school),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'status' => InvoiceStatus::Issued,
                'subtotal' => $baseAmount,
                'discount_total' => round($baseAmount - $effectiveAmount, 2),
                'total' => $effectiveAmount,
                'created_by' => $creator->id,
            ]);

            $invoice->items()->create([
                'fee_category_id' => $structure->fee_category_id,
                'fee_structure_id' => $structure->id,
                'description' => $structure->name,
                'quantity' => 1,
                'unit_amount' => $baseAmount,
                'amount' => $effectiveAmount,
            ]);

            return $invoice;
        });
    }

    /**
     * @param  array{amount: float, method: string, reference_number?: ?string, paid_at: string, notes?: ?string}  $data
     */
    public function recordPayment(Invoice $invoice, array $data, User $receivedBy): Payment
    {
        abort_if($invoice->status === InvoiceStatus::Void, 422, 'This invoice has been voided and can no longer accept payments.');
        abort_if($data['amount'] > $invoice->balance, 422, 'Payment amount exceeds the outstanding balance.');

        $payment = $this->gateway->record($invoice, $data, $receivedBy);

        $this->recalculate($invoice);

        return $payment;
    }

    /**
     * @param  array{amount: float, reason: string}  $data
     */
    public function issueCreditNote(Invoice $invoice, array $data, User $issuedBy): CreditNote
    {
        abort_if($invoice->status === InvoiceStatus::Void, 422, 'This invoice has been voided.');
        abort_if($data['amount'] > $invoice->balance, 422, 'Credit note amount exceeds the outstanding balance.');

        $creditNote = CreditNote::query()->create([
            'invoice_id' => $invoice->id,
            'credit_note_number' => $this->feeNumbers->nextCreditNoteNumber($invoice->school),
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'issued_by' => $issuedBy->id,
            'issued_at' => now()->toDateString(),
        ]);

        $this->recalculate($invoice);

        return $creditNote;
    }

    public function void(Invoice $invoice): void
    {
        abort_if($invoice->amount_paid > 0, 422, 'An invoice with recorded payments cannot be voided — issue a credit note instead.');

        $invoice->update(['status' => InvoiceStatus::Void]);
    }

    public function recalculate(Invoice $invoice): void
    {
        $amountPaid = (float) $invoice->payments()->sum('amount');
        $creditTotal = (float) $invoice->creditNotes()->sum('amount');

        $status = match (true) {
            $invoice->status === InvoiceStatus::Void => InvoiceStatus::Void,
            $amountPaid + $creditTotal >= $invoice->total && $invoice->total > 0 => InvoiceStatus::Paid,
            $amountPaid + $creditTotal > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Issued,
        };

        $invoice->update(['amount_paid' => $amountPaid, 'credit_total' => $creditTotal, 'status' => $status]);
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptData(Payment $payment): array
    {
        $payment->loadMissing(['invoice.student', 'receivedBy']);

        return [
            'payment' => [
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'method' => $payment->method->label(),
                'reference_number' => $payment->reference_number,
                'paid_at' => $payment->paid_at->toDateString(),
            ],
            'invoice' => [
                'invoice_number' => $payment->invoice->invoice_number,
                'total' => $payment->invoice->total,
                'balance' => $payment->invoice->balance,
            ],
            'student' => [
                'full_name' => $payment->invoice->student->full_name,
                'admission_number' => $payment->invoice->student->admission_number,
            ],
            'received_by' => $payment->receivedBy->full_name,
        ];
    }

    /**
     * The "Fees" tab / statement of account: every invoice for a student
     * with running totals, most recent first.
     *
     * @return array<string, mixed>
     */
    public function statementData(Student $student): array
    {
        $invoices = $student->invoices()->with(['items', 'payments', 'creditNotes'])->latest('issue_date')->get();

        return [
            'invoices' => $invoices,
            'summary' => [
                'total_billed' => round($invoices->sum('total'), 2),
                'total_paid' => round($invoices->sum('amount_paid'), 2),
                'total_credited' => round($invoices->sum('credit_total'), 2),
                'total_outstanding' => round($invoices->sum(fn ($invoice) => $invoice->balance), 2),
            ],
        ];
    }
}
