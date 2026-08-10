<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimetableEntryResource;
use App\Models\TimetableEntry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableController extends Controller
{
    /**
     * The weekly grid for a section or a teacher (exactly one of the two query params required).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TimetableEntry::class);

        if (! $request->filled('section_id') && ! $request->filled('teacher_id')) {
            throw ValidationException::withMessages(['section_id' => 'Provide either section_id or teacher_id.']);
        }

        $entries = TimetableEntry::query()
            ->with(['subject', 'teacher', 'room', 'period'])
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->integer('teacher_id')))
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->get();

        return ApiResponse::success(TimetableEntryResource::collection($entries));
    }
}
