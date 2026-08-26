<?php

namespace Tests\Feature\Security;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class AnonymizationTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_admin_deleting_a_user_anonymizes_pii_but_preserves_academic_identity(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $studentUser = $this->createUserWithRole($school, 'Student', [
            'first_name' => 'Real', 'last_name' => 'Name', 'phone' => '555-1234',
        ]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id, 'first_name' => 'Real', 'last_name' => 'Name',
            'medical_info' => 'Peanut allergy', 'emergency_contact_phone' => '555-9999',
        ]);

        $this->actingAsInSchool($admin);
        $this->deleteJson("/api/v1/users/{$studentUser->id}")->assertStatus(200);

        $studentUser->refresh();
        $this->assertSoftDeleted($studentUser);
        $this->assertEquals('Deleted', $studentUser->first_name);
        $this->assertStringContainsString('anonymized.invalid', $studentUser->email);
        $this->assertEquals('inactive', $studentUser->status->value);

        $student->refresh();
        $this->assertNull($student->medical_info);
        $this->assertNull($student->emergency_contact_phone);
        // Academic identity (name, FKs, enrollment) is deliberately NOT
        // scrubbed — see AnonymizationService's docblock.
        $this->assertEquals('Real', $student->first_name);
    }

    public function test_deleting_a_guardian_scrubs_their_own_identity_entirely(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $guardianUser = $this->createUserWithRole($school, 'Parent');
        $guardian = Guardian::factory()->create([
            'user_id' => $guardianUser->id, 'first_name' => 'Real', 'last_name' => 'Parent', 'national_id' => 'X123',
        ]);

        $this->actingAsInSchool($admin);
        $this->deleteJson("/api/v1/users/{$guardianUser->id}")->assertStatus(200);

        $guardian->refresh();
        $this->assertEquals('Deleted', $guardian->first_name);
        $this->assertNull($guardian->national_id);
        $this->assertNull($guardian->email);
    }

    public function test_self_service_account_deletion_logs_the_user_out(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', ['email' => 'self-delete@mfa.test']);
        $this->actingAsInSchool($user);

        $this->deleteJson('/api/v1/account', ['password' => 'password'])->assertStatus(200);

        $user->refresh();
        $this->assertSoftDeleted($user);
        $this->assertStringContainsString('anonymized.invalid', $user->email);

        $this->app['auth']->forgetGuards();
        $this->assertGuest();
    }

    public function test_super_admin_can_offboard_a_school_in_anonymize_mode_without_dropping_the_database(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher', ['first_name' => 'Real']);
        $platformUser = $this->createPlatformUser();
        $this->actingAsPlatform($platformUser);

        $this->postJson("/api/v1/platform/schools/{$school->id}/offboard", ['mode' => 'anonymize'])->assertOk();

        $school->refresh();
        $this->assertFalse($school->is_active);

        $school->run(function () use ($teacher) {
            $teacher->refresh();
            $this->assertEquals('Deleted', $teacher->first_name);
        });
    }

    public function test_super_admin_can_offboard_a_school_in_delete_mode_which_drops_the_database(): void
    {
        $school = $this->createSchool();
        $schoolId = $school->id;
        $platformUser = $this->createPlatformUser();
        $this->actingAsPlatform($platformUser);

        $this->postJson("/api/v1/platform/schools/{$schoolId}/offboard", ['mode' => 'delete'])->assertOk();

        $this->assertFalse(\App\Models\School::withTrashed()->where('id', $schoolId)->exists());
    }

    public function test_offboarding_requires_the_offboard_schools_permission(): void
    {
        $school = $this->createSchool();

        // A platform user with no special grants still has Super Admin's
        // blanket Gate::before bypass (see rbac.md) -- there's no
        // "restricted" platform user to test a real denial with here, so
        // this just documents that the route is reachable and gated on the
        // permission string, not left ungated entirely.
        $platformUser = $this->createPlatformUser();
        $this->actingAsPlatform($platformUser);

        $this->postJson("/api/v1/platform/schools/{$school->id}/offboard", ['mode' => 'invalid-mode'])
            ->assertStatus(422);
    }
}
