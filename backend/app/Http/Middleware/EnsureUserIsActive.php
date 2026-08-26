<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * LoginRequest already blocks a non-Active user from ever starting a new
 * session (UserStatus check there) — but that's the only place status was
 * ever checked. A user suspended mid-session kept every existing
 * request working indefinitely, since nothing re-checked status after
 * login. This closes that gap: PlatformUser has no status column (Super
 * Admin accounts aren't suspendable this way), so this only applies to the
 * tenant User guard.
 *
 * Structurally mirrors EnsureSchoolIsUsable/EnsureMfaEnrolled: invalidate
 * the session outright rather than just 403ing the current request, so the
 * cookie can't be replayed against a later request either.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->status === UserStatus::Active) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        throw new AuthenticationException('This account is no longer active. Contact your administrator.');
    }
}
