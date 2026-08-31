<?php

namespace Tests\Feature\Academic;

use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\School;
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
 * One test class covering all four SimpleLookupImport subclasses
 * (Departments, Grade Levels, Subjects, Rooms) — proves the shared base
 * class actually behaves correctly for each entity's own mapRow()/rules(),
 * not just that it compiles.
 */
class LookupImportTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function uploadedFile(array $rows, array $headings, string $name = 'lookup-import-test.xlsx'): UploadedFile
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

    public function test_admin_can_import_departments(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile(
            [['Mathematics & Science', 'MATSCI', 'Math and science subjects']],
            ['name', 'code', 'description'],
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertDatabaseHas('departments', ['code' => 'MATSCI']);
    }

    public function test_department_dry_run_creates_nothing(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile(
            [['Mathematics & Science', 'MATSCI', '']],
            ['name', 'code', 'description'],
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file, 'dry_run' => true], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertTrue($response->json('data.dry_run'));
        $this->assertDatabaseCount('departments', 0);
    }

    public function test_duplicate_department_code_within_the_file_fails_the_second_row(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile(
            [
                ['Math & Science', 'MATSCI', ''],
                ['Also Math', 'MATSCI', ''],
            ],
            ['name', 'code', 'description'],
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertDatabaseCount('departments', 1);
    }

    public function test_department_code_already_in_the_database_fails_the_row(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        Department::factory()->create(['code' => 'MATSCI']);

        $file = $this->uploadedFile(
            [['Math & Science', 'MATSCI', '']],
            ['name', 'code', 'description'],
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertDatabaseCount('departments', 1);
    }

    public function test_admin_can_import_grade_levels_with_sequence(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile(
            [['Grade 1', 'G1', '1']],
            ['name', 'code', 'sequence'],
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/grade-levels/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::query()->where('code', 'G1')->firstOrFail();
        $this->assertEquals(1, $gradeLevel->sequence);
    }

    public function test_admin_can_import_rooms_with_type(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $file = $this->uploadedFile(
            [['Room 101', 'R101', '30', 'lab']],
            ['name', 'code', 'capacity', 'type'],
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/rooms/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        tenancy()->initialize($school);
        $room = Room::query()->where('code', 'R101')->firstOrFail();
        $this->assertEquals('lab', $room->type->value);
        $this->assertEquals(30, $room->capacity);
    }

    public function test_admin_can_import_subjects_linked_to_a_department_by_code(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        Department::factory()->create(['code' => 'MATSCI']);

        $file = $this->uploadedFile(
            [['Mathematics', 'MATH', 'MATSCI', 'no']],
            ['name', 'code', 'department_code', 'is_elective'],
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/subjects/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        tenancy()->initialize($school);
        $subject = Subject::query()->where('code', 'MATH')->firstOrFail();
        $this->assertEquals('MATSCI', $subject->department->code);
        $this->assertFalse($subject->is_elective);
    }

    public function test_subject_import_fails_the_row_when_department_code_does_not_resolve(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $file = $this->uploadedFile(
            [['Mathematics', 'MATH', 'NOPE', 'no']],
            ['name', 'code', 'department_code', 'is_elective'],
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/subjects/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertDatabaseMissing('subjects', ['code' => 'MATH']);
    }

    public function test_import_requires_the_academic_structure_import_permission(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $file = $this->uploadedFile(
            [['Mathematics & Science', 'MATSCI', '']],
            ['name', 'code', 'description'],
        );

        $response = $this->actingAsInSchool($teacher)->post('/api/v1/departments/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertForbidden();
    }

    public function test_import_templates_can_be_downloaded_for_all_four_entities(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        foreach (['departments', 'grade-levels', 'subjects', 'rooms'] as $entity) {
            $this->actingAsInSchool($admin)->get("/api/v1/{$entity}/import/template")->assertOk();
        }
    }
}
