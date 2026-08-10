<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppNotificationResource;
use App\Models\AppNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AppNotificationController extends Controller
{
    /** Always the caller's own inbox — see AppNotificationPolicy's docblock. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AppNotification::class);

        $paginator = QueryBuilder::for(AppNotification::query()->where('user_id', $request->user()->id))
            ->allowedFilters(AllowedFilter::exact('is_read'))
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(AppNotificationResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'unread_count' => AppNotification::query()->where('user_id', $request->user()->id)->where('is_read', false)->count(),
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $request = request();
        $notification = AppNotification::query()->where('user_id', $request->user()->id)->findOrFail($id);

        $this->authorize('update', $notification);

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return ApiResponse::success(new AppNotificationResource($notification));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return ApiResponse::success(['message' => 'All notifications marked read.']);
    }
}
