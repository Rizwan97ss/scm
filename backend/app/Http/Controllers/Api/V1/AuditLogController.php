<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        // Super Admin (PlatformUser) can't reach this tenant-scoped route at
        // all, and Activity no longer has a school_id column (Sub-phase D)
        // -- every row visible via the current tenant connection already
        // belongs to this tenant, by construction.
        $paginator = QueryBuilder::for(Activity::query()->with('causer'))
            ->allowedFilters('log_name', 'event', AllowedFilter::exact('subject_type'))
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->paginate($request->integer('per_page', 25))
            ->appends($request->query());

        return ApiResponse::success(AuditLogResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
