<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the Spatie Permission "team" context for the request to the authenticated
 * user's school_id. Super Admins (school_id === null) are pinned to the reserved
 * team id 0, which represents the cross-school/global context and holds no real
 * `schools` row (Spatie's team_foreign_key columns carry no FK constraint).
 */
class EnsureSchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->school_id ?? 0);
        }

        return $next($request);
    }
}
