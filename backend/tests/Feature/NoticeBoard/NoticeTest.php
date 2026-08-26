<?php

namespace Tests\Feature\NoticeBoard;

use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class NoticeTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_school_admin_can_create_and_publish_a_notice(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $create = $this->actingAsInSchool($admin)->postJson('/api/v1/notices', [
            'title' => 'Sports Day', 'body' => 'Annual sports day next week.', 'type' => 'event',
            'audience' => 'all', 'event_date' => now()->addWeek()->toDateString(),
        ]);
        $create->assertCreated()->assertJsonPath('data.is_published', false);
        $noticeId = $create->json('data.id');

        $publish = $this->actingAsInSchool($admin)->postJson("/api/v1/notices/{$noticeId}/publish");
        $publish->assertOk()->assertJsonPath('data.is_published', true);
    }

    public function test_teacher_cannot_create_a_notice(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/notices', ['title' => 'X', 'body' => 'Y']);

        $response->assertStatus(403);
    }

    public function test_teacher_does_not_see_unpublished_notices_but_sees_published_ones(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        Notice::factory()->create(['is_published' => false]);
        tenancy()->initialize($school);
        Notice::factory()->create(['is_published' => true, 'audience' => 'all']);

        $response = $this->actingAsInSchool($teacher)->getJson('/api/v1/notices?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_notice_audience_filtering_hides_student_only_notices_from_parents(): void
    {
        $school = $this->createSchool();
        $parent = $this->createUserWithRole($school, 'Parent');
        tenancy()->initialize($school);
        Notice::factory()->create(['is_published' => true, 'audience' => 'students']);
        tenancy()->initialize($school);
        Notice::factory()->create(['is_published' => true, 'audience' => 'all']);

        $response = $this->actingAsInSchool($parent)->getJson('/api/v1/notices?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_principal_sees_draft_notices_via_oversight_view_permission(): void
    {
        $school = $this->createSchool();
        $principal = $this->createUserWithRole($school, 'Principal');
        tenancy()->initialize($school);
        Notice::factory()->create(['is_published' => false]);

        $response = $this->actingAsInSchool($principal)->getJson('/api/v1/notices?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cannot_publish_an_already_published_notice(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $notice = Notice::factory()->create(['is_published' => true]);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/notices/{$notice->id}/publish");

        $response->assertStatus(422);
    }
}
