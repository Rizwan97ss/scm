<?php

namespace Tests\Concerns;

use App\Http\Middleware\EnsureSessionPasswordIsCurrent;
use App\Models\Platform\PlatformUser;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolProvisioningService;
use Illuminate\Support\Facades\URL;

trait InteractsWithSchool
{
    /**
     * Remembers which school each user returned by createUserWithRole()
     * actually belongs to. actingAsInSchool() needs this rather than just
     * reading the currently-ambient tenant() -- in a multi-school test,
     * whatever was created *last* (e.g. seed data for a second school) can
     * leave tenancy pointed somewhere other than the user being acted as.
     * A WeakMap avoids both polluting the User model with a test-only
     * property and the spl_object_id() reuse hazard of a plain array.
     */
    private static ?\WeakMap $userSchools = null;

    private static function userSchools(): \WeakMap
    {
        return self::$userSchools ??= new \WeakMap();
    }

    /**
     * Creates a School (landlord row), which synchronously provisions a
     * real tenant database (School::$dispatchesEvents -> stancl's
     * CreateDatabase + MigrateDatabase job pipeline), then switches the
     * app into that tenant's connection for the rest of the test.
     */
    protected function createSchool(array $attributes = []): School
    {
        $school = School::factory()->create($attributes);

        tenancy()->initialize($school);

        // Points bare relative-path requests (postJson('/api/v1/auth/login'),
        // no acting-as user to derive a tenant from yet) at this school's own
        // subdomain, so tenancy.subdomain resolves it instead of rejecting
        // the request as hitting the central domain.
        URL::forceRootUrl($this->tenantUrl($school));

        app(SchoolProvisioningService::class)->seedDefaultRoles($school);

        return $school;
    }

    protected function createUserWithRole(School $school, string $role, array $attributes = []): User
    {
        tenancy()->initialize($school);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        self::userSchools()[$user] = $school;

        return $user;
    }

    /**
     * Landlord-connection Super Admin, on the separate `platform` guard.
     * Unlike a tenant User, this isn't scoped to any single school's
     * database — see actingAsPlatform().
     */
    protected function createPlatformUser(array $attributes = []): PlatformUser
    {
        return PlatformUser::factory()->create($attributes);
    }

    /**
     * Points subsequent relative-path test requests (getJson('/api/v1/...'))
     * at the given user's tenant subdomain, so the tenancy.subdomain
     * middleware resolves the correct School from the Host header exactly
     * as it would in production — then authenticates as that user.
     */
    protected function actingAsInSchool(User $user): static
    {
        $school = self::userSchools()[$user] ?? tenant();

        tenancy()->initialize($school);
        URL::forceRootUrl($this->tenantUrl($school));

        // actingAs() sets the guard's user directly (setUser()), bypassing
        // Auth::login() entirely, so it never runs the tenant-binding stash
        // every real login call site performs (see
        // EnsureSessionPasswordIsCurrent's docblock). Without this, every
        // request made by a test using this helper would fail its own
        // check and 401. withSession() reproduces what a real login for
        // this school would have stashed.
        return $this->actingAs($user)->withSession([EnsureSessionPasswordIsCurrent::SESSION_TENANT_KEY => $school->id]);
    }

    /**
     * Points subsequent relative-path test requests at the central
     * (non-tenant) domain and authenticates on the `platform` guard.
     */
    protected function actingAsPlatform(PlatformUser $user): static
    {
        // Platform routes never run tenancy.subdomain in production, so
        // tenancy is never active there either — a prior createSchool()/
        // createUserWithRole() call in the same test must not leak its
        // tenant connection into whatever runs next (e.g. Plan::factory()).
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        URL::forceRootUrl(config('app.url'));

        // See actingAsInSchool()'s comment — same reason, platform users
        // just never have a tenant to stash (null, matching a real
        // platform login).
        return $this->actingAs($user, 'platform')->withSession([EnsureSessionPasswordIsCurrent::SESSION_TENANT_KEY => null]);
    }

    private function tenantUrl(School $school): string
    {
        $central = parse_url(config('app.url'));
        $port = isset($central['port']) ? ':'.$central['port'] : '';

        return "{$central['scheme']}://{$school->slug}.{$central['host']}{$port}";
    }
}
