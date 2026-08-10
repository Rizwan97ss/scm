<?php

namespace Tests\Feature\Students;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class StudentImportExportTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_admin_can_import_students_with_partial_failure_report(): void
    {
        Storage::fake('local');

        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create(['is_current' => true]);
        $gradeLevel = GradeLevel::factory()->for($school)->create(['code' => 'G1']);
        Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'name' => 'A']);

        $rows = [
            ['Valid', 'Student', 'female', '2018-05-01', 'G1', 'A', '', now()->toDateString(), '', '', '', '', ''],
            ['Invalid', 'Student', 'female', '', 'G1', 'A', '', now()->toDateString(), '', '', '', '', ''],
        ];

        Excel::store(new class($rows) implements FromArray, WithHeadings
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return [
                    'first_name', 'last_name', 'gender', 'date_of_birth', 'grade_level_code', 'section_name',
                    'roll_number', 'admission_date', 'blood_group', 'nationality', 'previous_school_name',
                    'emergency_contact_name', 'emergency_contact_phone',
                ];
            }
        }, 'import-test.xlsx', 'local');

        $uploadedFile = new UploadedFile(
            Storage::disk('local')->path('import-test.xlsx'),
            'import-test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/students/import', [
            'file' => $uploadedFile,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertDatabaseHas('students', ['first_name' => 'Valid', 'school_id' => $school->id]);
        $this->assertDatabaseMissing('students', ['first_name' => 'Invalid']);
    }

    public function test_admin_can_export_students_to_excel(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        Student::factory()->for($school)->create([
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
        ]);

        $response = $this->actingAsInSchool($admin)->get('/api/v1/students/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type')
        );
    }

    public function test_import_template_can_be_downloaded(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->get('/api/v1/students/import/template');

        $response->assertOk();
    }
}
