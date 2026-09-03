<?php

namespace Tests\Feature\Communication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_any_authenticated_user_can_subscribe_with_no_special_permission(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-secret'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $teacher->id,
            'endpoint' => 'https://push.example.test/abc',
        ]);
    }

    public function test_subscribing_twice_with_the_same_endpoint_updates_the_row_instead_of_duplicating(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $this->actingAsInSchool($teacher)->postJson('/api/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'old-key', 'auth' => 'old-auth'],
        ])->assertCreated();

        $this->actingAsInSchool($teacher)->postJson('/api/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'new-key', 'auth' => 'new-auth'],
        ])->assertCreated();

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.test/abc', 'p256dh' => 'new-key']);
    }

    public function test_a_user_can_unsubscribe_by_endpoint(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $this->actingAsInSchool($teacher)->postJson('/api/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ])->assertCreated();

        $response = $this->actingAsInSchool($teacher)->deleteJson('/api/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.test/abc',
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.test/abc']);
    }

    public function test_a_user_cannot_delete_another_users_subscription(): void
    {
        $school = $this->createSchool();
        $teacherA = $this->createUserWithRole($school, 'Teacher');
        $teacherB = $this->createUserWithRole($school, 'Teacher');

        $this->actingAsInSchool($teacherA)->postJson('/api/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ])->assertCreated();

        $this->actingAsInSchool($teacherB)->deleteJson('/api/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.test/abc',
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.test/abc', 'user_id' => $teacherA->id]);
    }
}
