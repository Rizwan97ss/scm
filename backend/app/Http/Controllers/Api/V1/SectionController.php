<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Academic\StoreSectionRequest;
use App\Http\Requests\Academic\UpdateSectionRequest;
use App\Http\Resources\SectionResource;
use App\Models\Section;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SectionController extends CrudController
{
    protected array $allowedFilters = ['academic_year_id', 'grade_level_id'];

    protected array $allowedSorts = ['name'];

    protected string $defaultSort = 'name';

    protected array $with = ['gradeLevel', 'classTeacher', 'room'];

    protected function modelClass(): string
    {
        return Section::class;
    }

    protected function resourceClass(): string
    {
        return SectionResource::class;
    }

    public function store(StoreSectionRequest $request): JsonResponse
    {
        $this->authorize('create', Section::class);

        $section = Section::query()->create($request->validated());

        return ApiResponse::created(new SectionResource($section->load($this->with)));
    }

    public function update(UpdateSectionRequest $request, Section $section): JsonResponse
    {
        $this->authorize('update', $section);

        $section->update($request->validated());

        return ApiResponse::success(new SectionResource($section->load($this->with)));
    }
}
