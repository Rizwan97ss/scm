<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only bootstraps a session for requests it recognizes as coming
        // from the configured frontend (matched via Referer/Origin); bare test
        // HTTP calls have neither by default, which breaks session-touching
        // endpoints (login/logout) and is harmless everywhere else. Must match
        // SANCTUM_STATEFUL_DOMAINS, which (since Sub-phase B) is localtest.me-
        // based, not localhost — the bare (non-subdomain) entry there is what
        // this matches, since a test's active tenant subdomain varies per test.
        $this->withHeader('Referer', 'http://localtest.me:5173/');

        // Spatie's permission cache lives in the 'array' cache store, which (unlike
        // the database) is NOT reset by RefreshDatabase's per-test transaction
        // rollback — it's a plain in-memory array on a repository that survives for
        // the whole test process. Without this, roles/permissions created in one
        // test can leak stale cached data into the next.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // Defensive: a crashed prior run can leave tenant artifacts behind —
        // RefreshDatabase only rolls back the landlord DB, and both the
        // physical tenant sqlite file and any per-tenant storage directory
        // (FilesystemTenancyBootstrapper, see config/tenancy.php) live
        // outside that transaction.
        $this->deleteTenantArtifacts();
    }

    protected function tearDown(): void
    {
        // Tenancy state (active connection, current tenant) and the forced
        // root URL are both process-global — PHPUnit runs all tests in one
        // PHP process, so a test that never touches tenancy would otherwise
        // silently inherit whatever the previous test left active.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        URL::forceRootUrl(null);

        $this->deleteTenantArtifacts();

        parent::tearDown();
    }

    private function deleteTenantArtifacts(): void
    {
        foreach (glob(database_path('tenant*')) ?: [] as $file) {
            @unlink($file);
        }

        foreach (glob(storage_path('tenant*')) ?: [] as $dir) {
            File::deleteDirectory($dir);
        }
    }
}
