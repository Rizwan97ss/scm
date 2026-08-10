<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\School\StoreSchoolRequest;
use App\Http\Requests\School\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SchoolController extends CrudController
{
    protected array $allowedFilters = ['name', 'short_name'];

    protected array $allowedSorts = ['name'];

    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return School::class;
    }

    protected function resourceClass(): string
    {
        return SchoolResource::class;
    }

    public function store(StoreSchoolRequest $request): JsonResponse
    {
        $this->authorize('create', School::class);

        $school = School::query()->create($request->validated());

        return ApiResponse::created(new SchoolResource($school));
    }

    public function update(UpdateSchoolRequest $request, School $school): JsonResponse
    {
        $this->authorize('update', $school);

        $school->update($request->validated());

        return ApiResponse::success(new SchoolResource($school));
    }
}
