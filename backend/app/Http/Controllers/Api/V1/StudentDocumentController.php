<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentDocumentRequest;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StudentDocumentController extends Controller
{
    public function index(Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $documents = collect(Student::DOCUMENT_COLLECTIONS)
            ->flatMap(fn ($collection) => $student->getMedia($collection)->map(fn ($media) => [
                'id' => $media->id,
                'collection' => $collection,
                'file_name' => $media->file_name,
                'size' => $media->size,
                'url' => route('api.v1.media.show', $media),
                'created_at' => $media->created_at?->toIso8601String(),
            ]))
            ->values();

        return ApiResponse::success($documents);
    }

    public function store(StoreStudentDocumentRequest $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);

        $media = $student
            ->addMediaFromRequest('file')
            ->toMediaCollection($request->string('collection'));

        return ApiResponse::created([
            'id' => $media->id,
            'collection' => $media->collection_name,
            'file_name' => $media->file_name,
            'size' => $media->size,
            'url' => route('api.v1.media.show', $media),
        ]);
    }

    public function destroy(Student $student, int $media): JsonResponse
    {
        $this->authorize('update', $student);

        $mediaItem = $student->media()->findOrFail($media);
        $mediaItem->delete();

        return ApiResponse::noContent();
    }
}
