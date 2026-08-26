<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Fees\IssueCreditNoteRequest;
use App\Http\Requests\Fees\RecordPaymentRequest;
use App\Http\Requests\Fees\StoreInvoiceRequest;
use App\Http\Requests\Fees\UpdateInvoiceRequest;
use App\Http\Resources\CreditNoteResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InvoiceController extends CrudController
{
    private const WITH = ['student', 'items.feeCategory', 'payments', 'creditNotes', 'createdBy'];

    protected function modelClass(): string
    {
        return Invoice::class;
    }

    protected function resourceClass(): string
    {
        return InvoiceResource::class;
    }

    /**
     * Overrides CrudController::index() the same way HomeworkController/
     * ExamController do: the blanket invoices.view permission alone would
     * leak every school's invoice to a Student/Parent without applying
     * Invoice::scopeVisibleTo() first.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::query()->visibleTo($request->user())->with(self::WITH);

        $paginator = QueryBuilder::for($query)
            ->allowedFilters(AllowedFilter::exact('student_id'), AllowedFilter::exact('status'), AllowedFilter::exact('academic_year_id'), 'invoice_number')
            ->allowedSorts('due_date', 'issue_date', 'created_at')
            ->defaultSort('-issue_date')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(InvoiceResource::collection($paginator->items()), meta: $this->paginationMeta($paginator));
    }

    public function show(int $id): JsonResponse
    {
        $request = request();
        $invoice = Invoice::query()->with(self::WITH)->visibleTo($request->user())->findOrFail($id);

        $this->authorize('view', $invoice);

        return ApiResponse::success(new InvoiceResource($invoice));
    }

    public function store(StoreInvoiceRequest $request, InvoiceService $invoices): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $invoices->createInvoice($request->validated(), $request->user());

        return ApiResponse::created(new InvoiceResource($invoice->load(self::WITH)));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $invoice->update($request->validated());

        return ApiResponse::success(new InvoiceResource($invoice->load(self::WITH)));
    }

    /**
     * An invoice with recorded payments must be voided (see
     * InvoiceService::void()'s guard), not hard-deleted — this keeps the
     * payment ledger's foreign key meaningful. Only a never-paid invoice
     * (typically created by mistake) can actually be removed.
     */
    public function destroy(int $id): JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);

        $this->authorize('delete', $invoice);

        abort_if($invoice->amount_paid > 0, 422, 'An invoice with recorded payments cannot be deleted — void it instead.');

        $invoice->delete();

        return ApiResponse::noContent();
    }

    public function void(Invoice $invoice, InvoiceService $invoices): JsonResponse
    {
        $this->authorize('void', $invoice);

        $invoices->void($invoice);

        return ApiResponse::success(new InvoiceResource($invoice->fresh(self::WITH)));
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice, InvoiceService $invoices): JsonResponse
    {
        $this->authorize('recordPayment', $invoice);

        $payment = $invoices->recordPayment($invoice, $request->validated(), $request->user());

        return ApiResponse::created(new PaymentResource($payment->load(['student', 'receivedBy'])), 'Payment recorded.');
    }

    public function issueCreditNote(IssueCreditNoteRequest $request, Invoice $invoice, InvoiceService $invoices): JsonResponse
    {
        $this->authorize('issueCreditNote', $invoice);

        $creditNote = $invoices->issueCreditNote($invoice, $request->validated(), $request->user());

        return ApiResponse::created(new CreditNoteResource($creditNote->load('issuedBy')), 'Credit note issued.');
    }

    public function statement(Student $student, InvoiceService $invoices): JsonResponse
    {
        $this->authorize('view', $student);

        return ApiResponse::success($invoices->statementData($student));
    }
}
