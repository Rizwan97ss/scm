<?php

namespace App\Http\Middleware;

use App\Models\Platform\PlatformUser;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A minimal, hand-rolled equivalent of Laravel's built-in AuthenticateSession
 * middleware (session key + comparison logic mirrors it closely), needed
 * because the stock one doesn't work here: it calls $this->guard()->
 * viaRemember(), and Authenticate::shouldUse() switches the app's *default*
 * guard to 'sanctum' (a RequestGuard, which has no viaRemember()) for the
 * rest of the request once auth:sanctum has run — see
 * EnsureUserIsActive's own docblock for the same quirk playing out
 * elsewhere. Working off $request->user() directly (already resolved by
 * auth:sanctum/auth:platform before this runs) sidesteps guard resolution
 * entirely; there's no "remember me" cookie hash to separately validate
 * since this app doesn't set one (neither LoginController nor
 * PlatformLoginController ever call Auth::attempt with remember=true).
 *
 * Registered globally (bootstrap/app.php's appendToGroup('api', ...)),
 * mirroring EnsureUserIsActive, rather than per route-group in
 * routes/api.php — that group boundary would otherwise need duplicating
 * (there are two separate auth:sanctum groups: the auth/* one containing
 * `me`/`password`/MFA, and the broader app one) and platform routes need
 * the exact same protection PasswordController's tenant-side self-service
 * change gets.
 */
class EnsureSessionPasswordIsCurrent
{
    private const SESSION_KEY = 'password_hash';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $request->hasSession()) {
            return $next($request);
        }

        $guardName = $user instanceof PlatformUser ? 'platform' : 'web';
        $currentHash = $user->getAuthPassword();
        $storedHash = $request->session()->get(self::SESSION_KEY);

        if ($storedHash === null) {
            $request->session()->put(self::SESSION_KEY, $currentHash);

            return $next($request);
        }

        if (! hash_equals($currentHash, $storedHash)) {
            Auth::guard($guardName)->logout();
            $request->session()->invalidate();

            throw new AuthenticationException('Your session is no longer valid. Please log in again.');
        }

        $response = $next($request);

        // Re-stash on every request (not just when it was missing) so a
        // password change made *through this same session* (self-service
        // PasswordController) keeps that session alive — it naturally picks
        // up the new hash on its own very next request instead of also
        // getting logged out.
        if ($request->user()) {
            $request->session()->put(self::SESSION_KEY, $request->user()->getAuthPassword());
        }

        return $response;
    }
}
