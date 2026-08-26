<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Exam\StoreGradingScaleRequest;
use App\Http\Requests\Exam\UpdateGradingScaleRequest;
use App\Http\Resources\GradingScaleResource;
use App\Models\GradeBand;
use App\Models\GradingScale;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GradingScaleController extends CrudController
{
    protected array $allowedSorts = ['name', 'created_at'];

    protected string $defaultSort = 'name';

    protected array $with = ['gradeBands'];

    protected function modelClass(): string
    {
        return GradingScale::class;
    }

    protected function resourceClass(): string
    {
        return GradingScaleResource::class;
    }

    public function store(StoreGradingScaleRequest $request): JsonResponse
    {
        $this->authorize('create', GradingScale::class);

        $scale = DB::transaction(function () use ($request) {
            $scale = GradingScale::query()->create($request->safe()->except('grade_bands'));
            $this->syncBands($scale, $request->input('grade_bands', []));

            return $scale;
        });

        return ApiResponse::created(new GradingScaleResource($scale->load('gradeBands')));
    }

    public function update(UpdateGradingScaleRequest $request, GradingScale $gradingScale): JsonResponse
    {
        $this->authorize('update', $gradingScale);

        DB::transaction(function () use ($request, $gradingScale) {
            $gradingScale->update($request->safe()->except('grade_bands'));
            $this->syncBands($gradingScale, $request->input('grade_bands', []));
        });

        return ApiResponse::success(new GradingScaleResource($gradingScale->load('gradeBands')));
    }

    /**
     * Bands are always replaced wholesale — a scale is small (a handful of
     * bands), and diffing individual band edits isn't worth the complexity
     * a partial-update API would add.
     */
    private function syncBands(GradingScale $scale, array $bands): void
    {
        $scale->gradeBands()->delete();

        foreach ($bands as $band) {
            GradeBand::query()->create([
                'grading_scale_id' => $scale->id,
                'min_percentage' => $band['min_percentage'],
                'max_percentage' => $band['max_percentage'],
                'grade_label' => $band['grade_label'],
                'grade_point' => $band['grade_point'] ?? null,
                'remark' => $band['remark'] ?? null,
            ]);
        }
    }
}
