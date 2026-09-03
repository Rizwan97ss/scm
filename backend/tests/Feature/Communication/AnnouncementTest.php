<?php

namespace Tests\Feature\Communication;

use App\Jobs\SendPushJob;
use App\Jobs\SendSmsJob;
use App\Mail\AnnouncementMail;
use App\Models\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_school_admin_can_send_an_announcement_to_students_and_it_creates_in_app_notifications(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $studentA = $this->createUserWithRole($school, 'Student');
        $studentB = $this->createUserWithRole($school, 'Student');
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/announcements', [
            'title' => 'Holiday Notice', 'body' => 'School closed Friday.', 'audience' => 'students', 'channels' => ['in_app'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.recipient_count', 2);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $studentA->id, 'title' => 'Holiday Notice']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $studentB->id, 'title' => 'Holiday Notice']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $teacher->id]);
    }

    public function test_teacher_cannot_send_an_announcement(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/announcements', [
            'title' => 'X', 'body' => 'Y', 'audience' => 'all', 'channels' => ['in_app'],
        ]);

        $response->assertStatus(403);
    }

    public function test_email_channel_sends_mail_to_every_recipient_with_an_email(): void
    {
        Mail::fake();

        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $staff = $this->createUserWithRole($school, 'Teacher', ['email' => 'staff@example.test']);

        $this->actingAsInSchool($admin)->postJson('/api/v1/announcements', [
            'title' => 'Staff Meeting', 'body' => 'Meeting at 3pm.', 'audience' => 'staff', 'channels' => ['in_app', 'email'],
        ])->assertCreated();

        Mail::assertQueued(AnnouncementMail::class, fn ($mail) => $mail->recipient->id === $staff->id);
    }

    public function test_sms_channel_queues_a_job_per_recipient_with_a_phone_number(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin', ['phone' => null]);
        $this->createUserWithRole($school, 'Teacher', ['phone' => '+15551234567']);
        $this->createUserWithRole($school, 'Teacher', ['phone' => null]);

        Bus::fake();

        $this->actingAsInSchool($admin)->postJson('/api/v1/announcements', [
            'title' => 'Staff Meeting', 'body' => 'Meeting at 3pm.', 'audience' => 'staff', 'channels' => ['in_app', 'sms'],
        ])->assertCreated();

        Bus::assertDispatched(SendSmsJob::class, fn (SendSmsJob $job) => $job->phone === '+15551234567');
        Bus::assertDispatchedTimes(SendSmsJob::class, 1);
    }

    public function test_push_channel_queues_a_job_for_every_recipient(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $this->createUserWithRole($school, 'Student');
        $this->createUserWithRole($school, 'Student');

        Bus::fake();

        $this->actingAsInSchool($admin)->postJson('/api/v1/announcements', [
            'title' => 'Welcome', 'body' => 'Welcome back!', 'audience' => 'students', 'channels' => ['in_app', 'push'],
        ])->assertCreated();

        Bus::assertDispatchedTimes(SendPushJob::class, 2);
    }

    public function test_own_notification_inbox_can_be_marked_read(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $student = $this->createUserWithRole($school, 'Student');

        $this->actingAsInSchool($admin)->postJson('/api/v1/announcements', [
            'title' => 'Welcome', 'body' => 'Welcome back!', 'audience' => 'students', 'channels' => ['in_app'],
        ])->assertCreated();

        $notification = AppNotification::query()->where('user_id', $student->id)->firstOrFail();

        $index = $this->actingAsInSchool($student)->getJson('/api/v1/notifications');
        $index->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, $index->json('meta.unread_count'));

        $read = $this->actingAsInSchool($student)->postJson("/api/v1/notifications/{$notification->id}/read");
        $read->assertOk()->assertJsonPath('data.is_read', true);
    }

    public function test_a_user_cannot_mark_another_users_notification_read(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $studentA = $this->createUserWithRole($school, 'Student');
        $studentB = $this->createUserWithRole($school, 'Student');

        $this->actingAsInSchool($admin)->postJson('/api/v1/announcements', [
            'title' => 'Welcome', 'body' => 'Welcome back!', 'audience' => 'students', 'channels' => ['in_app'],
        ])->assertCreated();

        $notification = AppNotification::query()->where('user_id', $studentA->id)->firstOrFail();

        $response = $this->actingAsInSchool($studentB)->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(404);
    }
}
