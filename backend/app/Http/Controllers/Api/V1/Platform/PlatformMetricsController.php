<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Cross-tenant analytics for the platform console: landlord-side stats
 * (schools, billing, MRR) are cheap single queries; per-school usage
 * (students/staff/exams) requires one tenant-database switch per school
 * (School::usageSummary()), so this is O(schools) in tenant-DB round
 * trips. Fine at this app's current scale (a handful of schools); once
 * that stops being true, this needs to move behind a cache/scheduled
 * aggregation job instead of computing live on every dashboard load —
 * not a rewrite, just adding a cache layer in front of the same per-school
 * loop below.
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

        // Landlord-only query, no tenant switching — signups per month for
        // the last 12 months (including empty months, so the chart doesn't
        // silently skip a quiet month). Grouped in PHP rather than via
        // DATE_FORMAT/strftime: those aren't portable across MySQL (prod)
        // and SQLite (tests, per phpunit.xml), and a handful of schools'
        // worth of created_at timestamps is cheap to pull and bucket here.
        $signupCounts = School::query()
            ->where('created_at', '>=', Carbon::now()->startOfMonth()->subMonths(11))
            ->pluck('created_at')
            ->countBy(fn (Carbon $createdAt) => $createdAt->format('Y-m'));

        $schoolsByMonth = collect(range(11, 0))->map(function (int $monthsAgo) use ($signupCounts) {
            $month = Carbon::now()->startOfMonth()->subMonths($monthsAgo);

            return [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'count' => (int) ($signupCounts[$month->format('Y-m')] ?? 0),
            ];
        })->values();

        $schools = School::query()->with('plan')->orderByDesc('created_at')->get();

        $perSchool = $schools->map(function (School $school) {
            $usage = $school->usageSummary();

            return [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'plan_name' => $school->plan?->name,
                'billing_status' => $school->billing_status,
                'students' => $usage['students'],
                'staff' => $usage['staff'],
                'exams' => $usage['exams'],
                'created_at' => $school->created_at,
            ];
        });

        return ApiResponse::success([
            'total_schools' => $schools->count(),
            'total_students' => $perSchool->sum('students'),
            'total_staff' => $perSchool->sum('staff'),
            'total_exams' => $perSchool->sum('exams'),
            'by_billing_status' => $byStatus,
            'approximate_mrr_cents' => (int) $approximateMrrCents,
            'schools_by_month' => $schoolsByMonth,
            'schools' => $perSchool->values(),
        ]);
    }
}
