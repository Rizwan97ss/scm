<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Exam\StoreAssessmentComponentTypeRequest;
use App\Http\Requests\Exam\UpdateAssessmentComponentTypeRequest;
use App\Http\Resources\AssessmentComponentTypeResource;
use App\Models\AssessmentComponentType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AssessmentComponentTypeController extends CrudController
{
    protected array $allowedFilters = ['name', 'is_active'];

    protected array $allowedSorts = ['name', 'sequence'];

    protected string $defaultSort = 'sequence';

    protected function modelClass(): string
    {
        return AssessmentComponentType::class;
    }

    protected function resourceClass(): string
    {
        return AssessmentComponentTypeResource::class;
    }

    public function store(StoreAssessmentComponentTypeRequest $request): JsonResponse
    {
        $this->authorize('create', AssessmentComponentType::class);

        $componentType = AssessmentComponentType::query()->create($request->validated());

        return ApiResponse::created(new AssessmentComponentTypeResource($componentType));
    }

    public function update(UpdateAssessmentComponentTypeRequest $request, AssessmentComponentType $componentType): JsonResponse
    {
        $this->authorize('update', $componentType);

        $componentType->update($request->validated());

        return ApiResponse::success(new AssessmentComponentTypeResource($componentType));
    }
}
