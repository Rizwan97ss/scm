<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class SectionImportTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function uploadedFile(array $rows, string $name = 'section-import-test.xlsx'): UploadedFile
    {
        Storage::fake('local');
        $headings = ['grade_level_code', 'name', 'capacity', 'room_code'];

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

    public function test_admin_can_import_sections_with_a_room(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        AcademicYear::factory()->current()->create();
        tenancy()->initialize($school);
        GradeLevel::factory()->create(['code' => 'G1']);
        tenancy()->initialize($school);
        Room::factory()->create(['code' => 'R101']);

        $file = $this->uploadedFile([['G1', 'A', '30', 'R101']]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/sections/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $section = Section::query()->where('name', 'A')->firstOrFail();
        $this->assertEquals('G1', $section->gradeLevel->code);
        $this->assertEquals('R101', $section->room->code);
        $this->assertEquals(30, $section->capacity);
    }

    public function test_import_fails_without_a_current_academic_year(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        GradeLevel::factory()->create(['code' => 'G1']);

        $file = $this->uploadedFile([['G1', 'A', '', '']]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/sections/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    public function test_row_fails_when_grade_level_code_does_not_resolve(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        AcademicYear::factory()->current()->create();

        $file = $this->uploadedFile([['NOPE', 'A', '', '']]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/sections/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
    }

    public function test_same_section_name_under_different_grade_levels_is_not_a_duplicate(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        AcademicYear::factory()->current()->create();
        tenancy()->initialize($school);
        GradeLevel::factory()->create(['code' => 'G1']);
        tenancy()->initialize($school);
        GradeLevel::factory()->create(['code' => 'G2']);

        $file = $this->uploadedFile([
            ['G1', 'A', '', ''],
            ['G2', 'A', '', ''],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/sections/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.imported_count'));
        $this->assertDatabaseCount('sections', 2);
    }

    public function test_same_grade_level_and_name_within_the_file_is_a_duplicate(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        AcademicYear::factory()->current()->create();
        tenancy()->initialize($school);
        GradeLevel::factory()->create(['code' => 'G1']);

        $file = $this->uploadedFile([
            ['G1', 'A', '', ''],
            ['G1', 'A', '', ''],
        ]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/sections/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
    }

    public function test_row_fails_when_the_section_already_exists_in_the_database(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->current()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create(['code' => 'G1']);
        tenancy()->initialize($school);
        Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'name' => 'A']);

        $file = $this->uploadedFile([['G1', 'A', '', '']]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/sections/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertDatabaseCount('sections', 1);
    }

    public function test_dry_run_creates_nothing(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        AcademicYear::factory()->current()->create();
        tenancy()->initialize($school);
        GradeLevel::factory()->create(['code' => 'G1']);

        $file = $this->uploadedFile([['G1', 'A', '', '']]);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/sections/import', ['file' => $file, 'dry_run' => true], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertTrue($response->json('data.dry_run'));
        $this->assertDatabaseCount('sections', 0);
    }

    public function test_import_template_can_be_downloaded(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $this->actingAsInSchool($admin)->get('/api/v1/sections/import/template')->assertOk();
    }
}
