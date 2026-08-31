<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ImportLogResource;
use App\Models\ImportLog;
use App\Support\ApiResponse;
use App\Support\ImportUndoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Read-only browse of every import attempt (see ImportLogger) — reuses the
 * existing audit-logs.view permission rather than adding a new one, since
 * this is exactly that: an audit trail, just scoped to bulk imports instead
 * of general model changes.
 */
class ImportLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $paginator = QueryBuilder::for(ImportLog::query()->with('performedBy'))
            ->allowedFilters(AllowedFilter::exact('entity'), AllowedFilter::exact('dry_run'))
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(ImportLogResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /** Single-row fetch — what the frontend polls while a queued (large-file) import is still queued/processing. See ProcessStudentImportJob. */
    public function show(ImportLog $importLog): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        return ApiResponse::success(new ImportLogResource($importLog->load('performedBy')));
    }

    /**
     * Reverses an import — see ImportUndoService for exactly what "reverse"
     * means and which entities support it (the five lookup-table imports
     * only; nothing here changes for students/staff/guardians).
     */
    public function undo(ImportLog $importLog, ImportUndoService $service): JsonResponse
    {
        $this->authorize('undo', Activity::class);

        $result = $service->undo($importLog);

        $summary = "{$result['deleted']} record(s) undone.";
        if ($result['blocked']) {
            $summary .= ' '.count($result['blocked']).' could not be undone because something else now depends on them.';
        }

        return ApiResponse::success($result, $summary);
    }
}
