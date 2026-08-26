<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Single authenticated gate every uploaded file (student photos/documents,
 * homework attachments, user avatars, ...) is served through — Media's
 * `disk_name` is the private `local` disk (config/media-library.php,
 * MEDIA_DISK), not the old public one, so a URL alone no longer grants
 * access; this controller is what actually decides who gets the bytes.
 *
 * Deliberately generic rather than one controller per model: `$media->model`
 * is whatever polymorphic owner the file belongs to (Student, User,
 * Homework, ...), and `Gate::authorize('view', $media->model)` resolves to
 * that model's own Policy at runtime — so a Student's own photo is gated by
 * StudentPolicy, a Homework attachment by HomeworkPolicy, etc., without this
 * controller needing to know which. Adding a new media-bearing model needs
 * no change here, only that model's own Policy::view() to already be correct
 * (which every one of them already is — see the security-review pass this
 * came out of).
 */
class MediaController extends Controller
{
    public function show(Request $request, Media $media): BinaryFileResponse
    {
        $model = $media->model;

        abort_if(! $model, 404);

        Gate::forUser($request->user())->authorize('view', $model);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }
}
