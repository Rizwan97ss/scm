<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\NoticeBoard\StoreNoticeRequest;
use App\Http\Requests\NoticeBoard\UpdateNoticeRequest;
use App\Http\Resources\NoticeResource;
use App\Models\Notice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class NoticeController extends Controller
{
    private const WITH = ['createdBy'];

    /**
     * Anyone can hit this endpoint (NoticePolicy::viewAny is permissive) —
     * Notice::scopeVisibleTo() is what actually restricts a non-manager to
     * published, non-expired, audience-matching notices.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notice::class);

        $paginator = QueryBuilder::for(Notice::query()->visibleTo($request->user())->with(self::WITH))
            ->allowedFilters(AllowedFilter::exact('type'), AllowedFilter::exact('audience'), AllowedFilter::exact('is_published'))
            ->allowedSorts('event_date', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(NoticeResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $notice = Notice::query()->with(self::WITH)->visibleTo($request->user())->findOrFail($id);

        $this->authorize('view', $notice);

        return ApiResponse::success(new NoticeResource($notice));
    }

    public function store(StoreNoticeRequest $request): JsonResponse
    {
        $this->authorize('create', Notice::class);

        $notice = Notice::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        // fresh(), not load() — is_published/type/audience aren't in the
        // validated payload, so the in-memory model only has whatever
        // create() was explicitly given; their DB column defaults never
        // make it back onto the model without an actual re-fetch.
        return ApiResponse::created(new NoticeResource($notice->fresh(self::WITH)));
    }

    public function update(UpdateNoticeRequest $request, Notice $notice): JsonResponse
    {
        $this->authorize('update', $notice);

        $notice->update($request->validated());

        return ApiResponse::success(new NoticeResource($notice->load(self::WITH)));
    }

    public function destroy(Notice $notice): JsonResponse
    {
        $this->authorize('delete', $notice);

        $notice->delete();

        return ApiResponse::noContent();
    }

    public function publish(Notice $notice): JsonResponse
    {
        $this->authorize('publish', $notice);

        abort_if($notice->is_published, 422, 'This notice is already published.');

        $notice->update(['is_published' => true, 'published_at' => now()]);

        return ApiResponse::success(new NoticeResource($notice->load(self::WITH)));
    }
}
