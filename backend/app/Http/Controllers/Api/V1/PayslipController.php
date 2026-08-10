<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\GeneratePayrollRequest;
use App\Http\Resources\PayslipResource;
use App\Models\Payslip;
use App\Services\PayrollService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PayslipController extends Controller
{
    private const WITH = ['user'];

    public function __construct(private readonly PayrollService $payroll) {}

    /**
     * Anyone can hit this endpoint (PayslipPolicy::viewAny is permissive)
     * but only sees their own payslips unless they hold payroll.view — same
     * self-visibility shape as staff attendance / leave requests.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payslip::class);

        $query = Payslip::query()->with(self::WITH);

        if (! $request->user()->can('payroll.view')) {
            $query->where('user_id', $request->user()->id);
        }

        $paginator = QueryBuilder::for($query)
            ->allowedFilters(AllowedFilter::exact('user_id'), AllowedFilter::exact('status'), AllowedFilter::exact('year'), AllowedFilter::exact('month'))
            ->allowedSorts('year', 'month', 'created_at')
            ->defaultSort('-year', '-month')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(PayslipResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function generate(GeneratePayrollRequest $request): JsonResponse
    {
        $this->authorize('generate', Payslip::class);

        $result = $this->payroll->generateForMonth(tenant(), $request->integer('month'), $request->integer('year'), $request->user());

        return ApiResponse::success([
            'created_count' => $result['created']->count(),
            'skipped_count' => $result['skipped_count'],
        ], message: "Generated {$result['created']->count()} payslip(s); skipped {$result['skipped_count']} already-paid-this-period staff member(s).");
    }

    public function markPaid(int $id): JsonResponse
    {
        $request = request();
        $payslip = Payslip::query()->visibleTo($request->user())->findOrFail($id);

        $this->authorize('markPaid', $payslip);

        $this->payroll->markPaid($payslip);

        return ApiResponse::success(new PayslipResource($payslip->fresh(self::WITH)));
    }

    public function receipt(Request $request, int $payslip): JsonResponse
    {
        $record = Payslip::query()->visibleTo($request->user())->findOrFail($payslip);

        $this->authorize('view', $record);

        return ApiResponse::success($this->payroll->payslipData($record));
    }

    public function receiptPdf(Request $request, int $payslip): Response
    {
        $record = Payslip::query()->visibleTo($request->user())->findOrFail($payslip);

        $this->authorize('view', $record);

        $data = $this->payroll->payslipData($record);

        $pdf = Pdf::loadView('pdf.payslip', [
            'data' => $data,
            'schoolName' => tenant()->name,
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        $fileName = str($data['employee']['full_name'].'-'.$data['payslip']['payslip_number'])->slug().'-payslip.pdf';

        return $pdf->download($fileName);
    }
}
