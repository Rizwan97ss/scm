<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only bootstraps a session for requests it recognizes as coming
        // from the configured frontend (matched via Referer/Origin); bare test
        // HTTP calls have neither by default, which breaks session-touching
        // endpoints (login/logout) and is harmless everywhere else.
        $this->withHeader('Referer', 'http://localhost:5173/');

        // Spatie's permission cache lives in the 'array' cache store, which (unlike
        // the database) is NOT reset by RefreshDatabase's per-test transaction
        // rollback — it's a plain in-memory array on a repository that survives for
        // the whole test process. Without this, roles/permissions created in one
        // test can leak stale cached data into the next.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
