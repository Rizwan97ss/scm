<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Fees\StoreFeeCategoryRequest;
use App\Http\Requests\Fees\UpdateFeeCategoryRequest;
use App\Http\Resources\FeeCategoryResource;
use App\Models\FeeCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FeeCategoryController extends CrudController
{
    protected array $allowedFilters = ['name', 'is_active'];

    protected array $allowedSorts = ['name'];

    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return FeeCategory::class;
    }

    protected function resourceClass(): string
    {
        return FeeCategoryResource::class;
    }

    public function store(StoreFeeCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', FeeCategory::class);

        $feeCategory = FeeCategory::query()->create($request->validated());

        return ApiResponse::created(new FeeCategoryResource($feeCategory));
    }

    public function update(UpdateFeeCategoryRequest $request, FeeCategory $feeCategory): JsonResponse
    {
        $this->authorize('update', $feeCategory);

        $feeCategory->update($request->validated());

        return ApiResponse::success(new FeeCategoryResource($feeCategory));
    }
}
