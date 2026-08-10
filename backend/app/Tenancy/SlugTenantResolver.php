<?php

namespace App\Tenancy;

use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

/**
 * stancl/tenancy's default subdomain identification resolves against a
 * separate `domains` table (a tenant can have several arbitrary domains).
 * We deliberately don't have one — School.slug (already the basis for the
 * subdomain by convention, see School::booted()) IS the tenant key. Bound
 * in place of DomainTenantResolver in TenancyServiceProvider, since
 * InitializeTenancyBySubdomain's constructor is type-hinted to that
 * concrete class rather than an interface.
 */
class SlugTenantResolver extends DomainTenantResolver
{
    public function resolveWithoutCache(...$args): Tenant
    {
        $slug = $args[0];

        $tenant = config('tenancy.tenant_model')::query()->where('slug', $slug)->first();

        if ($tenant) {
            return $tenant;
        }

        throw new TenantCouldNotBeIdentifiedOnDomainException($slug);
    }

    /**
     * The parent implementation tracks which `Domain` row matched, via a
     * $tenant->domains relation School doesn't have — nothing to record
     * here, the slug itself already is the full identification.
     */
    public function resolved(Tenant $tenant, ...$args): void
    {
        //
    }

    /**
     * Only used by invalidateCache(), itself a no-op while $shouldCache is
     * false (inherited from DomainTenantResolver) — overridden anyway
     * rather than leaving the parent's $tenant->domains-based
     * implementation in place, which would break the moment caching gets
     * turned on here.
     */
    public function getArgsForTenant(Tenant $tenant): array
    {
        return [[$tenant->getAttribute('slug')]];
    }
}
