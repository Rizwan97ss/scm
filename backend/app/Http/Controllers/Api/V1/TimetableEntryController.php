<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreTimetableEntryRequest;
use App\Http\Requests\Academic\UpdateTimetableEntryRequest;
use App\Http\Resources\TimetableEntryResource;
use App\Models\TimetableEntry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TimetableEntryController extends Controller
{
    public function store(StoreTimetableEntryRequest $request): JsonResponse
    {
        $this->authorize('create', TimetableEntry::class);

        $entry = TimetableEntry::query()->create($request->validated());

        return ApiResponse::created(new TimetableEntryResource($entry->load(['subject', 'teacher', 'room', 'period'])));
    }

    public function update(UpdateTimetableEntryRequest $request, TimetableEntry $timetableEntry): JsonResponse
    {
        $this->authorize('update', $timetableEntry);

        $timetableEntry->update($request->validated());

        return ApiResponse::success(new TimetableEntryResource($timetableEntry->load(['subject', 'teacher', 'room', 'period'])));
    }

    public function destroy(TimetableEntry $timetableEntry): JsonResponse
    {
        $this->authorize('delete', $timetableEntry);

        $timetableEntry->delete();

        return ApiResponse::noContent();
    }
}
