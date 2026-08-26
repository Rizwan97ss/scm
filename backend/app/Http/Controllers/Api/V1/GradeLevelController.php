<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Academic\StoreGradeLevelRequest;
use App\Http\Requests\Academic\UpdateGradeLevelRequest;
use App\Http\Resources\GradeLevelResource;
use App\Models\GradeLevel;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class GradeLevelController extends CrudController
{
    protected array $allowedFilters = ['name'];

    protected array $allowedSorts = ['sequence', 'name'];

    protected string $defaultSort = 'sequence';

    protected function modelClass(): string
    {
        return GradeLevel::class;
    }

    protected function resourceClass(): string
    {
        return GradeLevelResource::class;
    }

    public function store(StoreGradeLevelRequest $request): JsonResponse
    {
        $this->authorize('create', GradeLevel::class);

        $gradeLevel = GradeLevel::query()->create($request->validated());

        return ApiResponse::created(new GradeLevelResource($gradeLevel));
    }

    public function update(UpdateGradeLevelRequest $request, GradeLevel $gradeLevel): JsonResponse
    {
        $this->authorize('update', $gradeLevel);

        $gradeLevel->update($request->validated());

        return ApiResponse::success(new GradeLevelResource($gradeLevel));
    }
}
