<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', [
            'email' => 'teacher@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'teacher@example.com')
            ->assertJsonPath('data.roles.0', 'Teacher');

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_user_can_login_with_username(): void
    {
        $school = $this->createSchool();
        $this->createUserWithRole($school, 'Student', [
            'username' => 'stu-001',
            'email' => 'stu-001@school.local',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'stu-001',
            'password' => 'correct-password',
        ]);

        $response->assertOk()->assertJsonPath('data.username', 'stu-001');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $school = $this->createSchool();
        $this->createUserWithRole($school, 'Teacher', [
            'email' => 'teacher@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $school = $this->createSchool();
        $this->createUserWithRole($school, 'Teacher', [
            'email' => 'teacher@example.com',
            'password' => Hash::make('correct-password'),
            'status' => 'suspended',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $school = $this->createSchool();
        $this->createUserWithRole($school, 'Teacher', [
            'email' => 'teacher@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'teacher@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Too many login attempts', $response->json('errors.email.0'));
    }
}
