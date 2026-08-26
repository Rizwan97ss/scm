<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces mandatory MFA (Phase 15) — every account, tenant or platform,
 * eventually needs a confirmed TOTP setup or every other endpoint 403s.
 *
 * A single class covers both guards: Laravel's Authenticate middleware
 * already resolved whichever guard succeeded before this runs (auth:sanctum
 * or auth:platform, both applied at the route-group level), so a plain
 * $request->user() — no guard argument — returns the right model either
 * way, the same trick EnsureSchoolIsUsable relies on for tenant().
 *
 * Structurally mirrors EnsureSchoolIsUsable: an EXEMPT_ROUTE_NAMES allowlist
 * for the handful of routes that must stay reachable even while locked out
 * (otherwise a user can never see why, or fix it), everything else blocked.
 */
class EnsureMfaEnrolled
{
    private const EXEMPT_ROUTE_NAMES = [
        'api.v1.auth.me',
        'api.v1.auth.logout',
        'api.v1.auth.mfa.setup',
        'api.v1.auth.mfa.confirm',
        'api.v1.auth.platform-me',
        'api.v1.auth.platform-logout',
        'api.v1.auth.platform-mfa.setup',
        'api.v1.auth.platform-mfa.confirm',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasMfaConfirmed()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::EXEMPT_ROUTE_NAMES, true)) {
            return $next($request);
        }

        if ($user->isWithinMfaGracePeriod()) {
            return $next($request);
        }

        // Not ApiResponse::error()'s $errors param (that's shaped for
        // per-field validation messages) — mfa_setup_required is a
        // top-level, machine-readable flag the frontend branches on to
        // redirect into MfaSetupPage rather than showing a generic error.
        return response()->json([
            'success' => false,
            'message' => 'Two-factor authentication is required on this account. Set it up to continue.',
            'data' => null,
            'mfa_setup_required' => true,
        ], 403);
    }
}
