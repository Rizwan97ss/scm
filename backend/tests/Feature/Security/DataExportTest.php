<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * QUEUE_CONNECTION=sync in testing (phpunit.xml) — GenerateDataExportJob
 * runs inline within the same request/test, no Queue::fake() needed to
 * observe its effect (the DataExport row is 'ready' by the time the
 * dispatching request returns).
 */
class DataExportTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_self_service_export_generates_and_downloads_only_the_requesters_own_data(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $this->actingAsInSchool($teacher);

        $store = $this->postJson('/api/v1/account/data-export')->assertCreated();
        $exportId = $store->json('data.id');
        $this->assertEquals('ready', $store->json('data.status'));

        $list = $this->getJson('/api/v1/account/data-export')->assertOk();
        $this->assertCount(1, $list->json('data'));

        $download = $this->get("/api/v1/data-exports/{$exportId}/download")->assertOk();
        $this->assertEquals('application/zip', $download->headers->get('Content-Type'));
    }

    public function test_school_wide_export_requires_the_data_export_school_permission(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $admin = $this->createUserWithRole($school, 'School Admin');

        $this->actingAsInSchool($teacher);
        $this->postJson('/api/v1/data-exports')->assertStatus(403);

        $this->actingAsInSchool($admin);
        $store = $this->postJson('/api/v1/data-exports')->assertCreated();
        $this->assertEquals('school', $store->json('data.scope'));
        $this->assertEquals('ready', $store->json('data.status'));
    }

    public function test_a_user_cannot_download_another_users_self_service_export(): void
    {
        $school = $this->createSchool();
        $owner = $this->createUserWithRole($school, 'Teacher');
        $otherUser = $this->createUserWithRole($school, 'Teacher');

        $this->actingAsInSchool($owner);
        $exportId = $this->postJson('/api/v1/account/data-export')->json('data.id');

        $this->actingAsInSchool($otherUser);
        $this->get("/api/v1/data-exports/{$exportId}/download")->assertStatus(403);
    }

    public function test_export_is_scoped_to_the_correct_tenant_database(): void
    {
        $schoolA = $this->createSchool();
        $adminA = $this->createUserWithRole($schoolA, 'School Admin');
        $this->actingAsInSchool($adminA);
        $exportId = $this->postJson('/api/v1/data-exports')->json('data.id');

        $schoolB = $this->createSchool();
        $adminB = $this->createUserWithRole($schoolB, 'School Admin');
        $this->actingAsInSchool($adminB);

        // The export row lives only in School A's tenant database — School
        // B's connection has no row with this id at all (404, not a leak).
        $this->get("/api/v1/data-exports/{$exportId}/download")->assertStatus(404);
    }
}
