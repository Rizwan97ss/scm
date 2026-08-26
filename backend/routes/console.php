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

// Phase 16 — backstop for online-test attempts nobody submitted (closed
// tab, lost connection). Coarser than the client-side countdown timer,
// which handles the live-student case; this just needs to run often
// enough that an abandoned attempt doesn't sit unscored for long.
Schedule::command('exams:auto-submit-expired')->everyFiveMinutes();
