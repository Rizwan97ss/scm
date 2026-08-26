<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentController extends Controller
{
    private const WITH = ['student', 'receivedBy', 'invoice'];

    /**
     * Staff roles holding invoices.view (School Admin, Principal,
     * Management, Accountant) get the school-wide ledger unfiltered —
     * Payment::scopeVisibleTo() only narrows for Student/Parent, mirroring
     * Invoice's own scope, so a child's guardian can't page through this
     * endpoint into another family's payments.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $paginator = QueryBuilder::for(Payment::query()->visibleTo($request->user())->with(self::WITH))
            ->allowedFilters(AllowedFilter::exact('student_id'), AllowedFilter::exact('invoice_id'), AllowedFilter::exact('method'), 'payment_number')
            ->allowedSorts('paid_at', 'created_at')
            ->defaultSort('-paid_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(PaymentResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function receipt(Request $request, int $payment, InvoiceService $invoices): JsonResponse
    {
        $record = Payment::query()->visibleTo($request->user())->findOrFail($payment);

        $this->authorize('view', $record);

        return ApiResponse::success($invoices->receiptData($record));
    }

    public function receiptPdf(Request $request, int $payment, InvoiceService $invoices): Response
    {
        $record = Payment::query()->visibleTo($request->user())->findOrFail($payment);

        $this->authorize('view', $record);

        $data = $invoices->receiptData($record);

        $pdf = Pdf::loadView('pdf.receipt', [
            'data' => $data,
            'schoolName' => tenant()->name,
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        $fileName = str($data['student']['full_name'].'-'.$data['payment']['payment_number'])->slug().'-receipt.pdf';

        return $pdf->download($fileName);
    }
}
