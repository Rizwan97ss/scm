<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateSchoolPlanRequest;
use App\Http\Resources\PlatformSchoolResource;
use App\Models\Plan;
use App\Models\School;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SchoolPlanController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * Changes a school's plan — same SubscriptionService::swapPlan() a
     * future School-Admin-facing "change plan" action (Sub-phase E) will
     * also call, so Stripe, the local plan_id, and the projected
     * billing.max_students/max_staff settings can never drift apart
     * depending on which side initiated the change.
     */
    public function update(UpdateSchoolPlanRequest $request, School $school): JsonResponse
    {
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        $this->subscriptions->swapPlan($school, $plan);

        return ApiResponse::success(new PlatformSchoolResource($school->fresh()->load('plan')), 'Plan updated.');
    }
}
