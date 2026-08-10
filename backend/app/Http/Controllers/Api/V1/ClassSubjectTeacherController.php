<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreClassSubjectTeacherRequest;
use App\Http\Resources\ClassSubjectTeacherResource;
use App\Models\ClassSubjectTeacher;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassSubjectTeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ClassSubjectTeacher::class);

        $assignments = ClassSubjectTeacher::query()
            ->with(['subject', 'teacher'])
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->get();

        return ApiResponse::success(ClassSubjectTeacherResource::collection($assignments));
    }

    public function store(StoreClassSubjectTeacherRequest $request): JsonResponse
    {
        $this->authorize('create', ClassSubjectTeacher::class);

        $assignment = ClassSubjectTeacher::query()->create($request->validated());

        return ApiResponse::created(new ClassSubjectTeacherResource($assignment->load(['subject', 'teacher'])));
    }

    public function destroy(ClassSubjectTeacher $classSubjectTeacher): JsonResponse
    {
        $this->authorize('delete', $classSubjectTeacher);

        $classSubjectTeacher->delete();

        return ApiResponse::noContent();
    }
}
