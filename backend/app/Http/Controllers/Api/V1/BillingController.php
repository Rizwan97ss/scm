<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformSchoolResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * School-scoped billing self-service — a School Admin's own plan/usage/
 * trial, and a link out to Stripe's hosted Customer Portal for card
 * updates and invoice history. Distinct from Api\V1\Platform\* (Super
 * Admin, cross-tenant). Both routes are on EnsureSchoolIsUsable's
 * allowlist — a school locked out for billing reasons must still be able
 * to see why and fix it.
 */
class BillingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorize('billing.view');

        $school = tenant()->loadMissing('plan');

        return ApiResponse::success(new PlatformSchoolResource($school));
    }

    public function portal(Request $request): JsonResponse
    {
        $this->authorize('billing.manage');

        $school = tenant();

        if (! $school->stripe_id) {
            return ApiResponse::error('Billing has not been set up for this school yet.', 422);
        }

        return ApiResponse::success(['url' => $school->billingPortalUrl($school->frontendUrl().'/settings/billing')]);
    }
}
