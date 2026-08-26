<?php

namespace App\Console\Commands;

use App\Models\Platform\PlatformUser;
use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off rollout command for Phase 15's mandatory MFA: every account
 * created from this point on gets a zero-length grace period (see User/
 * PlatformUser's `creating` hook — mfa_grace_period_ends_at defaults to
 * `now()`, since a brand-new signup is expected to set up MFA immediately).
 * Every account that already existed before this deploy would otherwise
 * be locked out of the app the instant EnsureMfaEnrolled goes live. Run
 * this once, right after deploying Sub-phase C, before real users hit it.
 *
 * Idempotent by construction — only touches accounts with no confirmed MFA
 * and no grace period already set, so re-running it never extends an
 * already-granted window or affects anyone who's since confirmed MFA.
 */
class GrantMfaGracePeriodCommand extends Command
{
    protected $signature = 'security:grant-mfa-grace-period {--days=14 : Grace period length in days}';

    protected $description = 'Grant every pre-existing account (tenant and platform) a one-time MFA setup grace period';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->addDays($days);

        $platformCount = PlatformUser::query()
            ->whereNull('two_factor_confirmed_at')
            ->whereNull('mfa_grace_period_ends_at')
            ->update(['mfa_grace_period_ends_at' => $cutoff]);

        $this->info("Platform users granted a grace period: {$platformCount}");

        School::all()->each(function (School $school) use ($cutoff) {
            // A School row with no physical database yet (mid-provisioning,
            // or a stale/orphaned record) would otherwise throw and abort
            // every school after it — same degrade-don't-crash guard
            // School::studentCount() already uses.
            if (! $school->database()->manager()->databaseExists($school->database()->getName())) {
                $this->warn("  {$school->name}: skipped (no physical database)");

                return;
            }

            $count = $school->run(fn () => User::query()
                ->whereNull('two_factor_confirmed_at')
                ->whereNull('mfa_grace_period_ends_at')
                ->update(['mfa_grace_period_ends_at' => $cutoff])
            );

            $this->line("  {$school->name}: {$count} user(s) granted a grace period");
        });

        return self::SUCCESS;
    }
}
