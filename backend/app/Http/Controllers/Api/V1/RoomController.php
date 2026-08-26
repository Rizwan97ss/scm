<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Academic\StoreRoomRequest;
use App\Http\Requests\Academic\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoomController extends CrudController
{
    protected array $allowedFilters = ['name', 'type'];

    protected array $allowedSorts = ['name'];

    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return Room::class;
    }

    protected function resourceClass(): string
    {
        return RoomResource::class;
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $this->authorize('create', Room::class);

        $room = Room::query()->create($request->validated());

        return ApiResponse::created(new RoomResource($room));
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $this->authorize('update', $room);

        $room->update($request->validated());

        return ApiResponse::success(new RoomResource($room));
    }
}
