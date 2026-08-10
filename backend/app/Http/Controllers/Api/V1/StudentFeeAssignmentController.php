<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Fees\StoreStudentFeeAssignmentRequest;
use App\Http\Requests\Fees\UpdateStudentFeeAssignmentRequest;
use App\Http\Resources\StudentFeeAssignmentResource;
use App\Models\StudentFeeAssignment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StudentFeeAssignmentController extends CrudController
{
    protected array $allowedFilters = ['student_id', 'fee_structure_id'];

    protected array $allowedSorts = ['created_at'];

    protected string $defaultSort = '-created_at';

    protected array $with = ['student', 'feeStructure'];

    protected function modelClass(): string
    {
        return StudentFeeAssignment::class;
    }

    protected function resourceClass(): string
    {
        return StudentFeeAssignmentResource::class;
    }

    public function store(StoreStudentFeeAssignmentRequest $request): JsonResponse
    {
        $this->authorize('create', StudentFeeAssignment::class);

        $assignment = StudentFeeAssignment::query()->create([
            ...$request->validated(),
        ]);

        return ApiResponse::created(new StudentFeeAssignmentResource($assignment->load($this->with)));
    }

    public function update(UpdateStudentFeeAssignmentRequest $request, StudentFeeAssignment $studentFeeAssignment): JsonResponse
    {
        $this->authorize('update', $studentFeeAssignment);

        $studentFeeAssignment->update($request->validated());

        return ApiResponse::success(new StudentFeeAssignmentResource($studentFeeAssignment->load($this->with)));
    }
}
