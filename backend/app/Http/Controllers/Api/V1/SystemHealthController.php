<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SystemHealthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SystemHealthController extends Controller
{
    public function index(SystemHealthService $health): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return ApiResponse::success($health->checks());
    }
}
