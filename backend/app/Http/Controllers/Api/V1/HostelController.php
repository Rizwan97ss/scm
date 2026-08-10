<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Hostel\StoreHostelRequest;
use App\Http\Requests\Hostel\UpdateHostelRequest;
use App\Http\Resources\HostelResource;
use App\Models\Hostel;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HostelController extends CrudController
{
    protected array $allowedFilters = ['name', 'type', 'is_active'];

    protected array $allowedSorts = ['name', 'created_at'];

    protected string $defaultSort = 'name';

    protected array $with = ['rooms'];

    protected function modelClass(): string
    {
        return Hostel::class;
    }

    protected function resourceClass(): string
    {
        return HostelResource::class;
    }

    public function store(StoreHostelRequest $request): JsonResponse
    {
        $this->authorize('create', Hostel::class);

        $hostel = Hostel::query()->create($request->validated());

        return ApiResponse::created(new HostelResource($hostel));
    }

    public function update(UpdateHostelRequest $request, Hostel $hostel): JsonResponse
    {
        $this->authorize('update', $hostel);

        $hostel->update($request->validated());

        return ApiResponse::success(new HostelResource($hostel));
    }
}
