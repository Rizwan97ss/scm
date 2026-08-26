<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\PlatformUserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success(new PlatformUserResource($request->user()));
    }
}
