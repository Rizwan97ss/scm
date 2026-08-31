<?php

namespace Tests\Feature\Academic;

use App\Models\Department;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class ImportModeAndLoggingTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function uploadedFile(array $rows, string $name = 'import-mode-test.xlsx'): UploadedFile
    {
        Storage::fake('local');
        $headings = ['name', 'code', 'description'];

        Excel::store(new class($rows, $headings) implements FromArray, WithHeadings
        {
            public function __construct(private array $rows, private array $headings) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        }, $name, 'local');

        return new UploadedFile(
            Storage::disk('local')->path($name),
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_create_mode_still_rejects_an_existing_code_by_default(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        Department::factory()->create(['code' => 'MATSCI', 'name' => 'Old Name']);

        $file = $this->uploadedFile([['New Name', 'MATSCI', '']]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(0, $response->json('data.updated_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertDatabaseHas('departments', ['code' => 'MATSCI', 'name' => 'Old Name']);
    }

    public function test_update_mode_updates_an_existing_record_and_never_creates_one(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        Department::factory()->create(['code' => 'MATSCI', 'name' => 'Old Name']);

        $file = $this->uploadedFile([['New Name', 'MATSCI', 'Updated description']]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $file,
            'mode' => 'update',
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.updated_count'));
        $this->assertDatabaseHas('departments', ['code' => 'MATSCI', 'name' => 'New Name', 'description' => 'Updated description']);
        $this->assertDatabaseCount('departments', 1);
    }

    public function test_update_mode_fails_the_row_when_nothing_exists_to_update(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $file = $this->uploadedFile([['Ghost', 'NOPE', '']]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $file,
            'mode' => 'update',
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(0, $response->json('data.updated_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertDatabaseCount('departments', 0);
    }

    public function test_upsert_mode_creates_when_missing_and_updates_when_present_in_the_same_file(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        Department::factory()->create(['code' => 'MATSCI', 'name' => 'Old Name']);

        $file = $this->uploadedFile([
            ['New Name', 'MATSCI', ''],   // existing -> update
            ['Humanities', 'HUM', ''],     // new -> create
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $file,
            'mode' => 'upsert',
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.updated_count'));
        $this->assertDatabaseHas('departments', ['code' => 'MATSCI', 'name' => 'New Name']);
        $this->assertDatabaseHas('departments', ['code' => 'HUM', 'name' => 'Humanities']);
        $this->assertDatabaseCount('departments', 2);
    }

    public function test_dry_run_upsert_reports_correct_counts_without_writing_anything(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        Department::factory()->create(['code' => 'MATSCI', 'name' => 'Old Name']);

        $file = $this->uploadedFile([
            ['New Name', 'MATSCI', ''],
            ['Humanities', 'HUM', ''],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $file,
            'mode' => 'upsert',
            'dry_run' => true,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.updated_count'));
        $this->assertDatabaseHas('departments', ['code' => 'MATSCI', 'name' => 'Old Name']); // untouched
        $this->assertDatabaseCount('departments', 1); // HUM never created
    }

    public function test_an_invalid_mode_value_is_rejected(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile([['New Name', 'MATSCI', '']]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $file,
            'mode' => 'delete-everything',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    public function test_every_import_attempt_is_logged_including_dry_runs(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $this->uploadedFile([['Math & Science', 'MATSCI', '']]),
            'dry_run' => true,
        ], ['Accept' => 'application/json'])->assertOk();

        tenancy()->initialize($school);
        $this->assertDatabaseCount('import_logs', 1);
        $log = ImportLog::query()->first();
        $this->assertEquals('department', $log->entity);
        $this->assertEquals($admin->id, $log->performed_by);
        $this->assertTrue($log->dry_run);
        $this->assertEquals(1, $log->created_count);
        $this->assertEquals('create', $log->mode);

        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $this->uploadedFile([['Math & Science', 'MATSCI', '']]),
        ], ['Accept' => 'application/json'])->assertOk();

        tenancy()->initialize($school);
        $this->assertDatabaseCount('import_logs', 2);
        $this->assertFalse(ImportLog::query()->latest('id')->first()->dry_run);
    }

    public function test_import_logs_are_browsable_via_the_api(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $this->uploadedFile([['Math & Science', 'MATSCI', '']]),
        ], ['Accept' => 'application/json'])->assertOk();

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/import-logs');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('department', $response->json('data.0.entity'));
        $this->assertEquals($admin->id, $response->json('data.0.performed_by.id'));
    }

    public function test_import_logs_require_the_audit_logs_view_permission(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $this->actingAsInSchool($teacher)->getJson('/api/v1/import-logs')->assertForbidden();
    }
}
