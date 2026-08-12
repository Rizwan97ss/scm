<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 15 — the first scheduled commands in this app (see docs/
// deployment.md § 6). Each fans out per-tenant itself (see the commands'
// own docblocks) rather than needing per-tenant Schedule:: entries here.
Schedule::command('retention:clean-activity-logs')->dailyAt('02:00');
Schedule::command('retention:clean-expired-exports')->hourly();
Schedule::command('retention:anonymize-stale-accounts')->dailyAt('03:00');
