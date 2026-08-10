<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * A handful of stat tiles, not a BI dashboard — deliberately scoped small
 * for a first pass (see docs/roadmap.md's Phase 6 notes).
 */
class PlatformMetricsController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('platform.view-metrics');

        $byStatus = School::query()
            ->selectRaw('billing_status, count(*) as count')
            ->groupBy('billing_status')
            ->pluck('count', 'billing_status');

        // Approximation, not true Stripe reporting: sums each active
        // school's current plan price at face value, ignoring proration,
        // discounts/coupons, and mid-cycle plan changes.
        $approximateMrrCents = School::query()
            ->where('billing_status', 'active')
            ->join('plans', 'schools.plan_id', '=', 'plans.id')
            ->sum('plans.price_cents');

        return ApiResponse::success([
            'total_schools' => School::query()->count(),
            'by_billing_status' => $byStatus,
            'approximate_mrr_cents' => (int) $approximateMrrCents,
        ]);
    }
}
