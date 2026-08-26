<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Academic\StoreDepartmentRequest;
use App\Http\Requests\Academic\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DepartmentController extends CrudController
{
    protected array $allowedFilters = ['name'];

    protected array $allowedSorts = ['name'];

    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function resourceClass(): string
    {
        return DepartmentResource::class;
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $department = Department::query()->create($request->validated());

        return ApiResponse::created(new DepartmentResource($department));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        return ApiResponse::success(new DepartmentResource($department));
    }
}
