<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates access based on a school's is_active flag and billing_status —
 * finally enforcing is_active, which has existed since Phase 0 as a
 * Super-Admin-editable column with nothing behind it until now.
 *
 * Reads the currently active tenant via tenant() (stancl/tenancy) rather
 * than a $user->school relation — there is no school_id column on User to
 * traverse anymore, isolation is physical. tenant() is null for any
 * request that never resolved a tenant (no subdomain middleware ran, e.g.
 * a platform/landlord-side request), which is exactly when this gate
 * should no-op — a School Admin's own subdomain sets tenant() before this
 * middleware runs; a Super Admin's platform-domain request never does.
 */
class EnsureSchoolIsUsable
{
    /**
     * Must stay reachable even when a school is locked out — otherwise a
     * School Admin can never see why, or fix it.
     */
    private const EXEMPT_ROUTE_NAMES = [
        'api.v1.auth.me',
        'api.v1.auth.logout',
        'api.v1.billing.show',
        'api.v1.billing.portal',
    ];

    /** Terminal states — Stripe's own Smart Retries already ran their course before reaching one of these. */
    private const LOCKED_STATUSES = ['canceled', 'unpaid', 'incomplete_expired'];

    public function handle(Request $request, Closure $next): Response
    {
        $school = tenant();

        if (! $school) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::EXEMPT_ROUTE_NAMES, true)) {
            return $next($request);
        }

        if (! $school->is_active) {
            return ApiResponse::error('This school has been deactivated. Contact your administrator.', 403);
        }

        if (in_array($school->billing_status, self::LOCKED_STATUSES, true)) {
            return ApiResponse::error("This school's subscription is inactive. An administrator needs to update billing to continue.", 402);
        }

        // A read-only grace period, not an immediate lock — Stripe's own
        // Smart Retries are still attempting the payment at this point.
        if ($school->billing_status === 'past_due' && ! $request->isMethodSafe()) {
            return ApiResponse::error('Payment is past due — this school is in read-only mode until billing is updated.', 402);
        }

        return $next($request);
    }
}
