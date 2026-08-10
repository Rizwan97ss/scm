<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Academic\StoreTimetablePeriodRequest;
use App\Http\Requests\Academic\UpdateTimetablePeriodRequest;
use App\Http\Resources\TimetablePeriodResource;
use App\Models\TimetablePeriod;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TimetablePeriodController extends CrudController
{
    protected array $allowedSorts = ['sequence'];

    protected string $defaultSort = 'sequence';

    protected function modelClass(): string
    {
        return TimetablePeriod::class;
    }

    protected function resourceClass(): string
    {
        return TimetablePeriodResource::class;
    }

    public function store(StoreTimetablePeriodRequest $request): JsonResponse
    {
        $this->authorize('create', TimetablePeriod::class);

        $period = TimetablePeriod::query()->create($request->validated());

        return ApiResponse::created(new TimetablePeriodResource($period));
    }

    public function update(UpdateTimetablePeriodRequest $request, TimetablePeriod $timetablePeriod): JsonResponse
    {
        $this->authorize('update', $timetablePeriod);

        $timetablePeriod->update($request->validated());

        return ApiResponse::success(new TimetablePeriodResource($timetablePeriod));
    }
}
