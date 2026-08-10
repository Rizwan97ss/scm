<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Fees\GenerateInvoicesRequest;
use App\Http\Requests\Fees\StoreFeeStructureRequest;
use App\Http\Requests\Fees\UpdateFeeStructureRequest;
use App\Http\Resources\FeeStructureResource;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FeeStructureController extends CrudController
{
    protected array $allowedFilters = ['name', 'academic_year_id', 'grade_level_id', 'fee_category_id', 'is_active'];

    protected array $allowedSorts = ['name', 'amount', 'created_at'];

    protected string $defaultSort = 'name';

    protected array $with = ['gradeLevel', 'feeCategory'];

    protected function modelClass(): string
    {
        return FeeStructure::class;
    }

    protected function resourceClass(): string
    {
        return FeeStructureResource::class;
    }

    public function store(StoreFeeStructureRequest $request): JsonResponse
    {
        $this->authorize('create', FeeStructure::class);

        $feeStructure = FeeStructure::query()->create($request->validated());

        return ApiResponse::created(new FeeStructureResource($feeStructure->load($this->with)));
    }

    public function update(UpdateFeeStructureRequest $request, FeeStructure $feeStructure): JsonResponse
    {
        $this->authorize('update', $feeStructure);

        $feeStructure->update($request->validated());

        return ApiResponse::success(new FeeStructureResource($feeStructure->load($this->with)));
    }

    public function generateInvoices(GenerateInvoicesRequest $request, FeeStructure $feeStructure, InvoiceService $invoices): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $result = $invoices->generateFromStructure(
            $feeStructure,
            $request->validated('section_id'),
            $request->validated('issue_date'),
            $request->validated('due_date'),
            $request->user(),
        );

        return ApiResponse::success([
            'created_count' => $result['created']->count(),
            'skipped_count' => $result['skipped_count'],
        ], message: "Generated {$result['created']->count()} invoice(s); skipped {$result['skipped_count']} already-invoiced student(s).");
    }
}
