<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Transport\StoreVehicleRequest;
use App\Http\Requests\Transport\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class VehicleController extends CrudController
{
    protected array $allowedFilters = ['registration_number', 'is_active'];

    protected array $allowedSorts = ['registration_number', 'created_at'];

    protected string $defaultSort = 'registration_number';

    protected function modelClass(): string
    {
        return Vehicle::class;
    }

    protected function resourceClass(): string
    {
        return VehicleResource::class;
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $vehicle = Vehicle::query()->create([
            ...$request->validated(),
        ]);

        return ApiResponse::created(new VehicleResource($vehicle));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $vehicle->update($request->validated());

        return ApiResponse::success(new VehicleResource($vehicle));
    }
}
