<?php

namespace Tests\Feature\Academic;

use App\Models\Department;
use App\Models\ImportLog;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * Undo is deliberately scoped to the five lookup-table imports (see
 * ImportUndoService's docblock) — it only ever removes rows a given import
 * actually created, never rows it updated, and only when nothing outside
 * that import now depends on them.
 */
class ImportUndoTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function uploadedFile(array $rows, array $headings, string $name = 'undo-test.xlsx'): UploadedFile
    {
        Storage::fake('local');

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

    public function test_undo_soft_deletes_the_records_an_import_created(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile([['Math & Science', 'MATSCI', '']], ['name', 'code', 'description']);
        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        tenancy()->initialize($school);
        $log = ImportLog::query()->where('entity', 'department')->firstOrFail();
        $department = Department::query()->where('code', 'MATSCI')->firstOrFail();

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/import-logs/{$log->id}/undo");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.deleted'));
        $this->assertEmpty($response->json('data.blocked'));
        $this->assertSoftDeleted('departments', ['id' => $department->id]);
        tenancy()->initialize($school);
        $this->assertNotNull($log->fresh()->undone_at);
    }

    public function test_undo_is_blocked_when_a_dependent_now_exists(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile([['Math & Science', 'MATSCI', '']], ['name', 'code', 'description']);
        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        tenancy()->initialize($school);
        $log = ImportLog::query()->where('entity', 'department')->firstOrFail();
        $department = Department::query()->where('code', 'MATSCI')->firstOrFail();
        tenancy()->initialize($school);
        Subject::factory()->create(['department_id' => $department->id]);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/import-logs/{$log->id}/undo");

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.deleted'));
        $this->assertCount(1, $response->json('data.blocked'));
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'deleted_at' => null]);
        tenancy()->initialize($school);
        $this->assertNotNull($log->fresh()->undone_at); // the attempt is still recorded — re-running won't retry the blocked one forever.
    }

    public function test_undo_is_partial_when_only_some_created_rows_have_dependents(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile(
            [
                ['Math & Science', 'MATSCI', ''],
                ['Arts', 'ARTS', ''],
            ],
            ['name', 'code', 'description'],
        );
        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        tenancy()->initialize($school);
        $log = ImportLog::query()->where('entity', 'department')->firstOrFail();
        $matSci = Department::query()->where('code', 'MATSCI')->firstOrFail();
        $arts = Department::query()->where('code', 'ARTS')->firstOrFail();
        tenancy()->initialize($school);
        Subject::factory()->create(['department_id' => $matSci->id]);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/import-logs/{$log->id}/undo");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.deleted'));
        $this->assertCount(1, $response->json('data.blocked'));
        $this->assertDatabaseHas('departments', ['id' => $matSci->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('departments', ['id' => $arts->id]);
    }

    public function test_undo_never_touches_a_row_the_import_only_updated(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $existing = Department::factory()->create(['code' => 'MATSCI', 'name' => 'Old Name']);

        $file = $this->uploadedFile(
            [
                ['New Name', 'MATSCI', ''],
                ['Arts', 'ARTS', ''],
            ],
            ['name', 'code', 'description'],
        );
        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $file,
            'mode' => 'upsert',
        ], ['Accept' => 'application/json'])->assertOk();

        tenancy()->initialize($school);
        $log = ImportLog::query()->where('entity', 'department')->firstOrFail();
        $this->assertEquals(1, $log->created_count);
        $this->assertEquals(1, $log->updated_count);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/import-logs/{$log->id}/undo");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.deleted'));
        $this->assertSoftDeleted('departments', ['code' => 'ARTS']);
        // The pre-existing, merely-updated row survives, with its update intact.
        $this->assertDatabaseHas('departments', ['id' => $existing->id, 'name' => 'New Name', 'deleted_at' => null]);
    }

    public function test_a_dry_run_import_cannot_be_undone(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile([['Math & Science', 'MATSCI', '']], ['name', 'code', 'description']);
        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', [
            'file' => $file,
            'dry_run' => true,
        ], ['Accept' => 'application/json'])->assertOk();

        tenancy()->initialize($school);
        $log = ImportLog::query()->where('entity', 'department')->firstOrFail();
        $this->assertTrue($log->dry_run);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/import-logs/{$log->id}/undo");

        $response->assertUnprocessable();
    }

    public function test_an_import_cannot_be_undone_twice(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile([['Math & Science', 'MATSCI', '']], ['name', 'code', 'description']);
        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        tenancy()->initialize($school);
        $log = ImportLog::query()->where('entity', 'department')->firstOrFail();
        $this->actingAsInSchool($admin)->postJson("/api/v1/import-logs/{$log->id}/undo")->assertOk();

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/import-logs/{$log->id}/undo");

        $response->assertUnprocessable();
    }

    public function test_undo_requires_the_audit_logs_manage_permission_not_just_view(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile([['Math & Science', 'MATSCI', '']], ['name', 'code', 'description']);
        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();
        tenancy()->initialize($school);
        $log = ImportLog::query()->where('entity', 'department')->firstOrFail();

        // Principal has audit-logs.view (can browse the import log list) but not audit-logs.manage — undo is a stricter, separate grant.
        $principal = $this->createUserWithRole($school, 'Principal');

        $response = $this->actingAsInSchool($principal)->postJson("/api/v1/import-logs/{$log->id}/undo");

        $response->assertForbidden();
    }

    public function test_import_log_response_exposes_can_undo(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile([['Math & Science', 'MATSCI', '']], ['name', 'code', 'description']);
        $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/import-logs');

        $response->assertOk();
        $this->assertTrue(collect($response->json('data'))->firstWhere('entity', 'department')['can_undo']);
    }
}
