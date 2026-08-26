<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Hr\StoreDesignationRequest;
use App\Http\Requests\Hr\UpdateDesignationRequest;
use App\Http\Resources\DesignationResource;
use App\Models\Designation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DesignationController extends CrudController
{
    protected array $allowedFilters = ['name', 'is_active'];

    protected array $allowedSorts = ['name'];

    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return Designation::class;
    }

    protected function resourceClass(): string
    {
        return DesignationResource::class;
    }

    public function store(StoreDesignationRequest $request): JsonResponse
    {
        $this->authorize('create', Designation::class);

        $designation = Designation::query()->create($request->validated());

        return ApiResponse::created(new DesignationResource($designation));
    }

    public function update(UpdateDesignationRequest $request, Designation $designation): JsonResponse
    {
        $this->authorize('update', $designation);

        $designation->update($request->validated());

        return ApiResponse::success(new DesignationResource($designation));
    }
}
