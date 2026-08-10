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

    public function test_school_specific_value_overrides_global_default(): void
    {
        $school = School::factory()->create();
        $service = app(SettingsService::class);

        $service->set('branding.primary_color', '#111111', null, SettingType::String, 'branding', true);
        $service->set('branding.primary_color', '#222222', $school->id, SettingType::String, 'branding', true);

        $this->assertEquals('#222222', $service->get('branding.primary_color', null, $school->id));

        $otherSchool = School::factory()->create();
        $this->assertEquals('#111111', $service->get('branding.primary_color', null, $otherSchool->id));
    }

    public function test_typed_values_are_cast_correctly(): void
    {
        $service = app(SettingsService::class);

        $service->set('students.admission_number_padding', 5, null, SettingType::Integer, 'students');
        $service->set('notifications.email_enabled', true, null, SettingType::Boolean, 'notifications');

        $this->assertSame(5, $service->get('students.admission_number_padding', null, null));
        $this->assertSame(true, $service->get('notifications.email_enabled', null, null));
    }

    public function test_only_public_settings_are_returned_by_public_for_school(): void
    {
        $service = app(SettingsService::class);

        $service->set('branding.primary_color', '#123456', null, SettingType::String, 'branding', true);
        $service->set('students.admission_number_format', '{YEAR}-{SEQ}', null, SettingType::String, 'students', false);

        $public = $service->publicForSchool(null);

        $this->assertArrayHasKey('branding.primary_color', $public);
        $this->assertArrayNotHasKey('students.admission_number_format', $public);
    }

    public function test_cache_is_invalidated_after_set(): void
    {
        $service = app(SettingsService::class);

        $service->set('branding.primary_color', '#111111', null, SettingType::String, 'branding', true);
        $this->assertEquals('#111111', $service->get('branding.primary_color'));

        $service->set('branding.primary_color', '#999999', null, SettingType::String, 'branding', true);
        $this->assertEquals('#999999', $service->get('branding.primary_color'));
    }
}
