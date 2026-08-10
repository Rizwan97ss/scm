<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use Illuminate\Support\Carbon;

/**
 * Modest financial reporting for Phase 8 — a collection summary and an
 * outstanding-dues breakdown. A fuller cross-module analytics/reports
 * surface is Phase 12; this stays scoped to what an Accountant needs day
 * to day, not a BI dashboard.
 */
class FeeReportService
{
    /**
     * @return array<string, mixed>
     */
    public function collectionSummary(School $school, ?string $fromDate, ?string $toDate): array
    {
        $from = $fromDate ? Carbon::parse($fromDate) : now()->startOfMonth();
        $to = $toDate ? Carbon::parse($toDate) : now()->endOfMonth();

        $payments = Payment::query()
            ->where('school_id', $school->id)
            ->whereDate('paid_at', '>=', $from->toDateString())
            ->whereDate('paid_at', '<=', $to->toDateString())
            ->get();

        $byMethod = $payments->groupBy(fn (Payment $payment) => $payment->method->value)
            ->map(fn ($group) => round($group->sum('amount'), 2));

        $byCategory = Invoice::query()
            ->where('school_id', $school->id)
            ->whereHas('payments', fn ($q) => $q->whereDate('paid_at', '>=', $from->toDateString())->whereDate('paid_at', '<=', $to->toDateString()))
            ->with('items.feeCategory')
            ->get()
            ->flatMap(fn (Invoice $invoice) => $invoice->items)
            ->groupBy(fn ($item) => $item->feeCategory?->name ?? 'Uncategorized')
            ->map(fn ($group) => round($group->sum('amount'), 2));

        return [
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'total_collected' => round($payments->sum('amount'), 2),
            'payment_count' => $payments->count(),
            'by_method' => $byMethod,
            'by_category' => $byCategory,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outstandingDues(School $school): array
    {
        $invoices = Invoice::query()
            ->where('school_id', $school->id)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->with(['student.currentGradeLevel', 'student.currentSection'])
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balance > 0);

        $byGrade = $invoices->groupBy(fn (Invoice $invoice) => $invoice->student->currentGradeLevel?->name ?? 'Unassigned')
            ->map(fn ($group) => round($group->sum(fn (Invoice $invoice) => $invoice->balance), 2));

        return [
            'total_outstanding' => round($invoices->sum(fn (Invoice $invoice) => $invoice->balance), 2),
            'overdue_count' => $invoices->filter(fn (Invoice $invoice) => $invoice->is_overdue)->count(),
            'invoice_count' => $invoices->count(),
            'by_grade_level' => $byGrade,
        ];
    }
}
