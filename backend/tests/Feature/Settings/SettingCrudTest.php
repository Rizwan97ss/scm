<?php

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class SettingCrudTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_school_admin_can_update_branding_settings(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'branding.primary_color', 'value' => '#ff0000', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals('#ff0000', $response->json('data')['branding.primary_color']);

        $this->assertDatabaseHas('settings', [
            'key' => 'branding.primary_color',
            'value' => '#ff0000',
        ]);
    }

    public function test_public_settings_endpoint_requires_no_auth_and_exposes_only_public_keys(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $this->actingAsInSchool($admin)->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'branding.primary_color', 'value' => '#00ff00', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
                ['key' => 'students.admission_number_format', 'value' => '{YEAR}-{SEQ}', 'type' => 'string', 'group' => 'students', 'is_public' => false],
            ],
        ])->assertOk();

        $response = $this->getJson("/api/v1/settings/public?school={$school->slug}");

        $response->assertOk();
        $publicSettings = $response->json('data');
        $this->assertEquals('#00ff00', $publicSettings['branding.primary_color']);
        $this->assertArrayNotHasKey('students.admission_number_format', $publicSettings);
    }

    public function test_teacher_cannot_update_settings(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'branding.primary_color', 'value' => '#000000', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
            ],
        ]);

        $response->assertStatus(403);
    }
}
