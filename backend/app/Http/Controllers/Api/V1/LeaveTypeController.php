<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Hr\StoreLeaveTypeRequest;
use App\Http\Requests\Hr\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LeaveTypeController extends CrudController
{
    protected array $allowedFilters = ['name', 'is_active'];

    protected array $allowedSorts = ['name'];

    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return LeaveType::class;
    }

    protected function resourceClass(): string
    {
        return LeaveTypeResource::class;
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize('create', LeaveType::class);

        $leaveType = LeaveType::query()->create([
            ...$request->validated(),
        ]);

        return ApiResponse::created(new LeaveTypeResource($leaveType));
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        $this->authorize('update', $leaveType);

        $leaveType->update($request->validated());

        return ApiResponse::success(new LeaveTypeResource($leaveType));
    }
}
