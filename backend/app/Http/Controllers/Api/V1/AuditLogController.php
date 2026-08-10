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

        $actor = $request->user();

        // TODO(tenancy): Super Admin detection needs PlatformUser (Sub-phase E) -- do not guess at a replacement.
        $paginator = QueryBuilder::for(Activity::query()->with('causer'))
            ->when($actor->school_id !== null, fn ($q) => $q->where('school_id', $actor->school_id))
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
