<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated — the signup wizard's pricing step. Not a
 * CrudController subclass: there's no per-school scoping (plans are
 * platform-level) and no create/update/delete surface here at all (that's
 * the platform admin console, Sub-phase D).
 */
class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->get();

        return ApiResponse::success(PlanResource::collection($plans));
    }
}
