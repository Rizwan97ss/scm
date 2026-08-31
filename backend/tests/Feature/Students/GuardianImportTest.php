<?php

namespace Tests\Feature\Students;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class GuardianImportTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function uploadedFile(array $rows, string $name = 'guardian-import-test.xlsx'): UploadedFile
    {
        Storage::fake('local');
        $headings = ['student_admission_number', 'first_name', 'last_name', 'email', 'phone', 'relationship_type', 'is_primary', 'can_pickup'];

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

    public function test_admin_can_attach_a_new_guardian_to_an_existing_student(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $student = Student::factory()->create(['admission_number' => '2026-0001']);

        $file = $this->uploadedFile([
            ['2026-0001', 'John', 'Doe', 'john.doe@example.com', '+1-555-0101', 'father', 'yes', 'yes'],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/guardians/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertCount(1, $student->fresh()->guardians);
        $link = $student->fresh()->guardians->first();
        $this->assertEquals('john.doe@example.com', $link->email);
        $this->assertEquals('father', $link->pivot->relationship_type->value);
        $this->assertTrue((bool) $link->pivot->is_primary);
    }

    /** The core behavior this import exists for: reuse an existing guardian by email rather than creating a duplicate person, same as StudentsImport's guardian slots already do. */
    public function test_an_existing_guardian_matched_by_email_is_reused_not_duplicated(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $existingGuardian = Guardian::factory()->create(['email' => 'shared.parent@example.com']);
        tenancy()->initialize($school);
        $sibling1 = Student::factory()->create(['admission_number' => '2026-0001']);
        tenancy()->initialize($school);
        $sibling2 = Student::factory()->create(['admission_number' => '2026-0002']);

        $file = $this->uploadedFile([
            ['2026-0001', 'Ignored', 'Name', 'shared.parent@example.com', '', 'father', 'yes', 'yes'],
            ['2026-0002', 'Ignored', 'Name', 'shared.parent@example.com', '', 'father', 'yes', 'yes'],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/guardians/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.imported_count'));
        $this->assertDatabaseCount('guardians', 1);
        $this->assertCount(1, $sibling1->fresh()->guardians);
        $this->assertCount(1, $sibling2->fresh()->guardians);
        $this->assertEquals($existingGuardian->id, $sibling1->fresh()->guardians->first()->id);
    }

    public function test_create_mode_rejects_a_link_that_already_exists(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $student = Student::factory()->create(['admission_number' => '2026-0001']);
        tenancy()->initialize($school);
        $guardian = Guardian::factory()->create(['email' => 'john.doe@example.com']);
        $student->guardians()->attach($guardian->id, ['relationship_type' => 'father', 'is_primary' => true, 'can_pickup' => true]);

        $file = $this->uploadedFile([
            ['2026-0001', 'John', 'Doe', 'john.doe@example.com', '', 'father', 'yes', 'yes'],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/guardians/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
    }

    public function test_update_mode_updates_the_existing_pivot_instead_of_failing(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $student = Student::factory()->create(['admission_number' => '2026-0001']);
        tenancy()->initialize($school);
        $guardian = Guardian::factory()->create(['email' => 'john.doe@example.com']);
        $student->guardians()->attach($guardian->id, ['relationship_type' => 'father', 'is_primary' => false, 'can_pickup' => false]);

        $file = $this->uploadedFile([
            ['2026-0001', 'John', 'Doe', 'john.doe@example.com', '', 'father', 'yes', 'yes'],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/guardians/import', [
            'file' => $file,
            'mode' => 'update',
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.updated_count'));
        $link = $student->fresh()->guardians->first();
        $this->assertTrue((bool) $link->pivot->is_primary);
        $this->assertTrue((bool) $link->pivot->can_pickup);
    }

    public function test_row_fails_when_the_admission_number_does_not_resolve(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $file = $this->uploadedFile([
            ['NOPE', 'John', 'Doe', 'john.doe@example.com', '', 'father', 'yes', 'yes'],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/guardians/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertDatabaseCount('guardians', 0);
    }

    public function test_dry_run_creates_no_guardian_and_no_link(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $student = Student::factory()->create(['admission_number' => '2026-0001']);

        $file = $this->uploadedFile([
            ['2026-0001', 'John', 'Doe', 'new.guardian@example.com', '', 'father', 'yes', 'yes'],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/guardians/import', [
            'file' => $file,
            'dry_run' => true,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertTrue($response->json('data.dry_run'));
        $this->assertDatabaseCount('guardians', 0);
        $this->assertCount(0, $student->fresh()->guardians);
    }

    public function test_import_requires_the_guardians_import_permission(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $file = $this->uploadedFile([['2026-0001', 'John', 'Doe', 'john.doe@example.com', '', 'father', 'yes', 'yes']]);

        $this->actingAsInSchool($teacher)->post('/api/v1/guardians/import', ['file' => $file], ['Accept' => 'application/json'])->assertForbidden();
    }

    /** Same route-declaration-order class of bug as users/export — guardians/import must not be shadowed by guardians/{guardian}. */
    public function test_import_template_route_is_not_shadowed_by_the_show_route(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $this->actingAsInSchool($admin)->get('/api/v1/guardians/import/template')->assertOk();
    }
}
