<?php

namespace Tests\Unit\Services;

use App\Enums\SettingType;
use App\Models\School;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * There is no more global-default-vs-school-override merge (see
     * SettingsService's own docblock) — each tenant has its own physically
     * separate `settings` table, so this is what "override" collapses to
     * now: two schools setting the same key never interact at all.
     */
    public function test_settings_are_isolated_per_tenant(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $service = app(SettingsService::class);

        // The schoolId param below only namespaces the settings CACHE key —
        // the actual row read/written is already scoped by whichever
        // tenant connection is active. Passing it here (rather than null,
        // as the single-school tests below do) matters specifically in
        // this multi-school test: two schools both caching under the same
        // "settings:global" key would make one tenant's read return the
        // other's cached value, since CACHE_STORE isn't tenant-scoped
        // (see config/tenancy.php's CacheTenancyBootstrapper note).
        $schoolA->run(function () use ($service, $schoolA) {
            $service->set('branding.primary_color', '#111111', $schoolA->id, SettingType::String, 'branding', true);
        });
        $schoolB->run(function () use ($service, $schoolB) {
            $service->set('branding.primary_color', '#222222', $schoolB->id, SettingType::String, 'branding', true);
        });

        $schoolA->run(function () use ($service, $schoolA) {
            $this->assertEquals('#111111', $service->get('branding.primary_color', null, $schoolA->id));
        });
        $schoolB->run(function () use ($service, $schoolB) {
            $this->assertEquals('#222222', $service->get('branding.primary_color', null, $schoolB->id));
        });
    }

    public function test_typed_values_are_cast_correctly(): void
    {
        $school = School::factory()->create();
        $service = app(SettingsService::class);

        $school->run(function () use ($service) {
            $service->set('students.admission_number_padding', 5, null, SettingType::Integer, 'students');
            $service->set('notifications.email_enabled', true, null, SettingType::Boolean, 'notifications');

            $this->assertSame(5, $service->get('students.admission_number_padding'));
            $this->assertSame(true, $service->get('notifications.email_enabled'));
        });
    }

    public function test_only_public_settings_are_returned_by_public_for_school(): void
    {
        $school = School::factory()->create();
        $service = app(SettingsService::class);

        $school->run(function () use ($service) {
            $service->set('branding.primary_color', '#123456', null, SettingType::String, 'branding', true);
            $service->set('students.admission_number_format', '{YEAR}-{SEQ}', null, SettingType::String, 'students', false);

            $public = $service->publicForSchool(null);

            $this->assertArrayHasKey('branding.primary_color', $public);
            $this->assertArrayNotHasKey('students.admission_number_format', $public);
        });
    }

    public function test_cache_is_invalidated_after_set(): void
    {
        $school = School::factory()->create();
        $service = app(SettingsService::class);

        $school->run(function () use ($service) {
            $service->set('branding.primary_color', '#111111', null, SettingType::String, 'branding', true);
            $this->assertEquals('#111111', $service->get('branding.primary_color'));

            $service->set('branding.primary_color', '#999999', null, SettingType::String, 'branding', true);
            $this->assertEquals('#999999', $service->get('branding.primary_color'));
        });
    }
}
