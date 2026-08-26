<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DataExportScope;
use App\Enums\DataExportStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DataExportResource;
use App\Jobs\GenerateDataExportJob;
use App\Models\DataExport;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Both the self-service ("export my own data") and admin-bulk ("export
 * the whole school") flows, sharing the same DataExport model/job/service —
 * only authorization and the `scope` column differ, see DataExportService's
 * own docblock. Deliberately one controller, not two, for that reason.
 */
class DataExportController extends Controller
{
    public function storeSelf(Request $request): JsonResponse
    {
        $export = $this->createAndDispatch(DataExportScope::Self, $request->user()->id);

        return ApiResponse::created(new DataExportResource($export), 'Export started — check back in a moment.');
    }

    public function indexSelf(Request $request): JsonResponse
    {
        $exports = DataExport::query()
            ->where('scope', DataExportScope::Self)
            ->where('requested_by', $request->user()->id)
            ->latest()
            ->get();

        return ApiResponse::success(DataExportResource::collection($exports));
    }

    public function storeSchool(Request $request): JsonResponse
    {
        $this->authorize('data-export.school');

        $export = $this->createAndDispatch(DataExportScope::School, $request->user()->id);

        return ApiResponse::created(new DataExportResource($export), 'School-wide export started — check back in a moment.');
    }

    public function indexSchool(): JsonResponse
    {
        $this->authorize('data-export.school');

        $exports = DataExport::query()->where('scope', DataExportScope::School)->with('requestedBy')->latest()->get();

        return ApiResponse::success(DataExportResource::collection($exports));
    }

    public function download(Request $request, DataExport $export): StreamedResponse|JsonResponse
    {
        if ($export->scope === DataExportScope::Self) {
            if ($export->requested_by !== $request->user()->id) {
                abort(403);
            }
        } else {
            $this->authorize('data-export.school');
        }

        if ($export->status !== DataExportStatus::Ready || ! $export->file_path) {
            return ApiResponse::error('This export is not ready to download yet.', 409);
        }

        if (! Storage::disk('local')->exists($export->file_path)) {
            return ApiResponse::error('This export has expired and is no longer available.', 410);
        }

        return Storage::disk('local')->download($export->file_path, "data-export-{$export->id}.zip");
    }

    private function createAndDispatch(DataExportScope $scope, int $requestedBy): DataExport
    {
        $export = DataExport::query()->create([
            'scope' => $scope,
            'status' => DataExportStatus::Pending,
            'requested_by' => $requestedBy,
        ]);

        GenerateDataExportJob::dispatch($export->id);

        // QUEUE_CONNECTION=sync in testing (and a real worker processing
        // near-instantly in practice) means the job may have already run
        // by the time dispatch() returns — refresh so the response
        // reflects 'ready', not the stale 'pending' this PHP object was
        // created with, still held in memory from before the job's own
        // DataExport::find()->update() calls happened on a separate
        // instance of this same row.
        return $export->fresh();
    }
}
