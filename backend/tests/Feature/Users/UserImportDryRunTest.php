<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class UserImportDryRunTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function uploadRows(array $rows): UploadedFile
    {
        Storage::fake('local');

        $headings = ['first_name', 'last_name', 'email', 'role', 'phone', 'designation_name', 'employee_id', 'hire_date'];

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
        }, 'user-import-dry-run-test.xlsx', 'local');

        return new UploadedFile(
            Storage::disk('local')->path('user-import-dry-run-test.xlsx'),
            'user-import-dry-run-test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_dry_run_reports_the_same_counts_but_creates_nothing(): void
    {
        Notification::fake();
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $file = $this->uploadRows([
            ['Jane', 'Doe', 'jane.doe@example.com', 'Teacher', '', '', '', ''],
            ['Bad', 'Row', 'jane.doe@example.com', 'Teacher', '', '', '', ''], // duplicate email -> failure
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/users/import', [
            'file' => $file,
            'dry_run' => true,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertTrue($response->json('data.dry_run'));
        $this->assertStringContainsString('would be imported', $response->json('message'));

        // The critical assertion: dry run must not have created the user.
        $this->assertDatabaseMissing('users', ['email' => 'jane.doe@example.com']);
        Notification::assertNothingSent();
    }

    public function test_committing_after_a_dry_run_actually_creates_the_user(): void
    {
        Notification::fake();
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $file = $this->uploadRows([
            ['Jane', 'Doe', 'jane.doe@example.com', 'Teacher', '', '', '', ''],
        ]);

        $this->actingAsInSchool($admin)->post('/api/v1/users/import', ['file' => $file, 'dry_run' => true], ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseMissing('users', ['email' => 'jane.doe@example.com']);

        // Same file object, committed for real — same UI flow as clicking "Confirm" after a preview.
        $response = $this->actingAsInSchool($admin)->post('/api/v1/users/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertFalse($response->json('data.dry_run'));
        tenancy()->initialize($school);
        $user = User::query()->where('email', 'jane.doe@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Teacher'));
    }

    /**
     * The privilege-escalation guard must still fire during a dry run, not
     * just at commit time — otherwise HR Staff would see a misleading
     * "1 would be imported" preview for a row the real import will reject,
     * or worse, the preview/commit paths could drift apart over time.
     */
    public function test_dry_run_still_blocks_restricted_role_escalation(): void
    {
        Notification::fake();
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');

        $file = $this->uploadRows([
            ['Evil', 'Admin', 'evil.admin@example.com', 'School Admin', '', '', '', ''],
        ]);

        $response = $this->actingAsInSchool($hr)->post('/api/v1/users/import', [
            'file' => $file,
            'dry_run' => true,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
    }
}
