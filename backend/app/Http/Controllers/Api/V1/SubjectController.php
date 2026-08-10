<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Academic\StoreSubjectRequest;
use App\Http\Requests\Academic\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SubjectController extends CrudController
{
    protected array $allowedFilters = ['name', 'department_id'];

    protected array $allowedSorts = ['name'];

    protected string $defaultSort = 'name';

    protected array $with = ['department'];

    protected function modelClass(): string
    {
        return Subject::class;
    }

    protected function resourceClass(): string
    {
        return SubjectResource::class;
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $this->authorize('create', Subject::class);

        $subject = Subject::query()->create($request->validated());

        return ApiResponse::created(new SubjectResource($subject));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        $this->authorize('update', $subject);

        $subject->update($request->validated());

        return ApiResponse::success(new SubjectResource($subject));
    }
}
