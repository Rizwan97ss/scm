<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Academic\StoreTermRequest;
use App\Http\Requests\Academic\UpdateTermRequest;
use App\Http\Resources\TermResource;
use App\Models\Term;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TermController extends CrudController
{
    protected array $allowedFilters = ['academic_year_id'];

    protected array $allowedSorts = ['sequence', 'start_date'];

    protected string $defaultSort = 'sequence';

    protected function modelClass(): string
    {
        return Term::class;
    }

    protected function resourceClass(): string
    {
        return TermResource::class;
    }

    public function store(StoreTermRequest $request): JsonResponse
    {
        $this->authorize('create', Term::class);

        $term = Term::query()->create($request->validated());

        return ApiResponse::created(new TermResource($term));
    }

    public function update(UpdateTermRequest $request, Term $term): JsonResponse
    {
        $this->authorize('update', $term);

        $term->update($request->validated());

        return ApiResponse::success(new TermResource($term));
    }
}
