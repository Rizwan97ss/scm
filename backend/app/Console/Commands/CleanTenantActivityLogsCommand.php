<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Spatie's own `activitylog:clean` reads one global config('activitylog.
 * clean_after_days') and has no concept of iterating tenant databases
 * (where `activity_log` actually lives, per-tenant, since the tenancy
 * conversion). A bare `Schedule::command('activitylog:clean')` would run
 * against whatever tenant happens to be "current" when the scheduler
 * ticks — none, since the scheduler process isn't inside any tenant
 * context — and silently no-op forever. This fans out per-tenant instead,
 * the same $school->run(fn () => ...) pattern used everywhere else in this
 * codebase for a tenant-context switch, passing each school's own
 * retention.activity_log_days setting (falling back to the package's
 * 365-day default) via the command's own --days option.
 */
class CleanTenantActivityLogsCommand extends Command
{
    protected $signature = 'retention:clean-activity-logs';

    protected $description = 'Prune old activity log entries in every tenant database, per that tenant\'s own retention setting';

    public function handle(SettingsService $settings): int
    {
        School::all()->each(function (School $school) use ($settings) {
            $school->run(function () use ($settings) {
                $days = (int) $settings->get('retention.activity_log_days', 365);
                Artisan::call('activitylog:clean', ['--days' => $days, '--force' => true]);
            });
        });

        return self::SUCCESS;
    }
}
