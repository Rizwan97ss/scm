<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Hostel\StoreHostelRoomRequest;
use App\Http\Requests\Hostel\UpdateHostelRoomRequest;
use App\Http\Resources\HostelRoomResource;
use App\Models\HostelRoom;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HostelRoomController extends CrudController
{
    protected array $allowedFilters = ['room_number', 'hostel_id', 'is_active'];

    protected array $allowedSorts = ['room_number', 'created_at'];

    protected string $defaultSort = 'room_number';

    protected array $with = ['hostel'];

    protected function modelClass(): string
    {
        return HostelRoom::class;
    }

    protected function resourceClass(): string
    {
        return HostelRoomResource::class;
    }

    public function store(StoreHostelRoomRequest $request): JsonResponse
    {
        $this->authorize('create', HostelRoom::class);

        $room = HostelRoom::query()->create($request->validated());

        return ApiResponse::created(new HostelRoomResource($room->load($this->with)));
    }

    public function update(UpdateHostelRoomRequest $request, HostelRoom $hostelRoom): JsonResponse
    {
        $this->authorize('update', $hostelRoom);

        $hostelRoom->update($request->validated());

        return ApiResponse::success(new HostelRoomResource($hostelRoom->load($this->with)));
    }
}
