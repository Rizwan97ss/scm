<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Exam\StoreExamTypeRequest;
use App\Http\Requests\Exam\UpdateExamTypeRequest;
use App\Http\Resources\ExamTypeResource;
use App\Models\ExamType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ExamTypeController extends CrudController
{
    protected array $allowedFilters = ['name', 'is_active'];

    protected array $allowedSorts = ['name', 'sequence'];

    protected string $defaultSort = 'sequence';

    protected function modelClass(): string
    {
        return ExamType::class;
    }

    protected function resourceClass(): string
    {
        return ExamTypeResource::class;
    }

    public function store(StoreExamTypeRequest $request): JsonResponse
    {
        $this->authorize('create', ExamType::class);

        $examType = ExamType::query()->create($request->validated());

        return ApiResponse::created(new ExamTypeResource($examType));
    }

    public function update(UpdateExamTypeRequest $request, ExamType $examType): JsonResponse
    {
        $this->authorize('update', $examType);

        $examType->update($request->validated());

        return ApiResponse::success(new ExamTypeResource($examType));
    }
}
