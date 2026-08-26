<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * This stancl/tenancy version (v3.10) doesn't ship a BroadcastTenancyBootstrapper
 * -- there's no automatic per-tenant channel-name prefixing. That matters here
 * specifically because every tenant shares the same Pusher app: tenant DB user
 * IDs restart at 1 per school, so a bare `user.{id}` channel would let School
 * A's user 1 and School B's user 1 both authorize onto the identical channel
 * name, leaking one tenant's notifications to another's user. `tenant()->id`
 * (the School's own landlord primary key) never resets and is what actually
 * makes this channel unique per tenant -- the redundant tenant()->id check
 * inside the callback (on top of the route parameter) guards against ever
 * authorizing a channel for a school other than the one currently resolved
 * by tenancy.subdomain for this request.
 */
Broadcast::channel('tenant.{schoolId}.user.{userId}', function (User $user, int $schoolId, int $userId) {
    return tenant()
        && tenant()->id === $schoolId
        && $user->id === $userId;
});
