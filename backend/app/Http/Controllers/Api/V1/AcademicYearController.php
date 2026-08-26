<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Academic\StoreAcademicYearRequest;
use App\Http\Requests\Academic\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends CrudController
{
    protected array $allowedFilters = ['name', 'status'];

    protected array $allowedSorts = ['name', 'start_date', 'created_at'];

    protected string $defaultSort = '-start_date';

    protected function modelClass(): string
    {
        return AcademicYear::class;
    }

    protected function resourceClass(): string
    {
        return AcademicYearResource::class;
    }

    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $this->authorize('create', AcademicYear::class);

        $year = AcademicYear::query()->create($request->validated());

        return ApiResponse::created(new AcademicYearResource($year));
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $this->authorize('update', $academicYear);

        $academicYear->update($request->validated());

        return ApiResponse::success(new AcademicYearResource($academicYear));
    }

    /**
     * Marks this academic year as the school's current one and unmarks every other.
     */
    public function activate(AcademicYear $academicYear): JsonResponse
    {
        $this->authorize('update', $academicYear);

        DB::transaction(function () use ($academicYear) {
            AcademicYear::query()->update(['is_current' => false]);
            $academicYear->update(['is_current' => true, 'status' => 'active']);
        });

        return ApiResponse::success(new AcademicYearResource($academicYear->fresh()), 'Academic year activated.');
    }
}
