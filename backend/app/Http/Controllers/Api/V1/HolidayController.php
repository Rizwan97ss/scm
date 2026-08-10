<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Academic\StoreHolidayRequest;
use App\Http\Requests\Academic\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HolidayController extends CrudController
{
    protected array $allowedFilters = ['academic_year_id', 'type'];

    protected array $allowedSorts = ['start_date'];

    protected string $defaultSort = 'start_date';

    protected function modelClass(): string
    {
        return Holiday::class;
    }

    protected function resourceClass(): string
    {
        return HolidayResource::class;
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $this->authorize('create', Holiday::class);

        $holiday = Holiday::query()->create($request->validated());

        return ApiResponse::created(new HolidayResource($holiday));
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        $this->authorize('update', $holiday);

        $holiday->update($request->validated());

        return ApiResponse::success(new HolidayResource($holiday));
    }
}
