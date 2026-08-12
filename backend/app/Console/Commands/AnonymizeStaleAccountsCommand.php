<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\School;
use App\Models\User;
use App\Services\AnonymizationService;
use App\Services\SettingsService;
use Illuminate\Console\Command;

/**
 * Off by default (retention.inactive_account_anonymize_days is null) —
 * auto-anonymizing accounts on a timer is aggressive enough that a school
 * has to explicitly opt in via Settings, unlike the other two retention
 * commands which have a sane always-on default. Only touches Active
 * accounts; anyone already Inactive/Suspended is left alone (either
 * already anonymized, or deliberately deactivated for an unrelated
 * reason this command shouldn't second-guess).
 */
class AnonymizeStaleAccountsCommand extends Command
{
    protected $signature = 'retention:anonymize-stale-accounts';

    protected $description = 'Anonymize accounts inactive past a school\'s configured threshold (opt-in, off by default)';

    public function handle(SettingsService $settings, AnonymizationService $anonymization): int
    {
        School::all()->each(function (School $school) use ($settings, $anonymization) {
            $school->run(function () use ($settings, $anonymization) {
                $days = $settings->get('retention.inactive_account_anonymize_days');

                if (! $days) {
                    return;
                }

                $cutoff = now()->subDays((int) $days);

                User::query()
                    ->where('status', UserStatus::Active)
                    ->where(function ($query) use ($cutoff) {
                        $query->where('last_login_at', '<', $cutoff)
                            ->orWhere(fn ($q) => $q->whereNull('last_login_at')->where('created_at', '<', $cutoff));
                    })
                    ->get()
                    ->each(fn (User $user) => $anonymization->anonymizeUser($user));
            });
        });

        return self::SUCCESS;
    }
}
