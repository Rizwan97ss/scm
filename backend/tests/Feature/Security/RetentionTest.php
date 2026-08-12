<?php

namespace Tests\Feature\Security;

use App\Enums\SettingType;
use App\Enums\UserStatus;
use App\Models\DataExport;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_clean_activity_logs_respects_each_schools_own_retention_setting(): void
    {
        $school = $this->createSchool();
        tenancy()->initialize($school);

        app(SettingsService::class)->set('retention.activity_log_days', 30, null, SettingType::Integer, 'retention');

        $old = Activity::create(['log_name' => 'default', 'description' => 'old', 'created_at' => now()->subDays(60)]);
        $recent = Activity::create(['log_name' => 'default', 'description' => 'recent', 'created_at' => now()->subDays(5)]);

        $this->artisan('retention:clean-activity-logs')->assertExitCode(0);

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_clean_expired_exports_removes_the_file_and_the_row(): void
    {
        $school = $this->createSchool();
        tenancy()->initialize($school);

        $disk = Storage::disk('local');
        $disk->put('data-exports/expired.zip', 'fake zip contents');

        $expired = DataExport::query()->create([
            'scope' => 'self', 'status' => 'ready', 'requested_by' => $this->createUserWithRole($school, 'Teacher')->id,
            'file_path' => 'data-exports/expired.zip', 'expires_at' => now()->subDay(),
        ]);

        $this->artisan('retention:clean-expired-exports')->assertExitCode(0);

        $this->assertModelMissing($expired);
        $this->assertFalse($disk->exists('data-exports/expired.zip'));
    }

    public function test_anonymize_stale_accounts_is_a_no_op_when_the_setting_is_unset(): void
    {
        $school = $this->createSchool();
        $staleUser = $this->createUserWithRole($school, 'Teacher', [
            'first_name' => 'Real', 'last_login_at' => now()->subYears(2),
        ]);

        $this->artisan('retention:anonymize-stale-accounts')->assertExitCode(0);

        $this->assertEquals('Real', $staleUser->fresh()->first_name);
    }

    public function test_anonymize_stale_accounts_anonymizes_past_the_configured_threshold_only(): void
    {
        $school = $this->createSchool();
        tenancy()->initialize($school);
        app(SettingsService::class)->set('retention.inactive_account_anonymize_days', 90, null, SettingType::Integer, 'retention');

        $staleUser = $this->createUserWithRole($school, 'Teacher', [
            'first_name' => 'Real', 'last_login_at' => now()->subDays(120),
        ]);
        $recentUser = $this->createUserWithRole($school, 'Teacher', [
            'first_name' => 'Recent', 'last_login_at' => now()->subDays(10),
        ]);
        $alreadyInactive = $this->createUserWithRole($school, 'Teacher', [
            'first_name' => 'Already', 'status' => UserStatus::Inactive, 'last_login_at' => now()->subDays(500),
        ]);

        $this->artisan('retention:anonymize-stale-accounts')->assertExitCode(0);

        $this->assertEquals('Deleted', $staleUser->fresh()->first_name);
        $this->assertEquals('Recent', $recentUser->fresh()->first_name);
        // Already-inactive accounts are left alone — see the command's own docblock.
        $this->assertEquals('Already', $alreadyInactive->fresh()->first_name);
    }
}
