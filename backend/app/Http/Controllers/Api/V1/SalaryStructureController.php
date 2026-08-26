<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Hr\StoreSalaryStructureRequest;
use App\Http\Requests\Hr\UpdateSalaryStructureRequest;
use App\Http\Resources\SalaryStructureResource;
use App\Models\SalaryStructure;
use App\Services\PayrollService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SalaryStructureController extends CrudController
{
    protected array $allowedFilters = ['user_id', 'is_active'];

    protected array $allowedSorts = ['effective_from', 'created_at'];

    protected string $defaultSort = '-effective_from';

    protected array $with = ['user'];

    protected function modelClass(): string
    {
        return SalaryStructure::class;
    }

    protected function resourceClass(): string
    {
        return SalaryStructureResource::class;
    }

    public function store(StoreSalaryStructureRequest $request, PayrollService $payroll): JsonResponse
    {
        $this->authorize('create', SalaryStructure::class);

        $data = $request->validated();
        $payroll->replaceActiveStructure($data['user_id'], $data['effective_from']);

        $structure = SalaryStructure::query()->create([
            ...$data,
            'is_active' => true,
        ]);

        return ApiResponse::created(new SalaryStructureResource($structure->load($this->with)));
    }

    public function update(UpdateSalaryStructureRequest $request, SalaryStructure $salaryStructure): JsonResponse
    {
        $this->authorize('update', $salaryStructure);

        $salaryStructure->update($request->validated());

        return ApiResponse::success(new SalaryStructureResource($salaryStructure->load($this->with)));
    }
}
