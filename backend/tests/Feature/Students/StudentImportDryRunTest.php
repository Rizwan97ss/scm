<?php

namespace Tests\Feature\Students;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class StudentImportDryRunTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_dry_run_previews_the_import_without_creating_students_or_guardians(): void
    {
        Storage::fake('local');

        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create(['is_current' => true]);
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create(['code' => 'G1']);
        tenancy()->initialize($school);
        Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'name' => 'A']);

        $headings = [
            'first_name', 'last_name', 'gender', 'date_of_birth', 'grade_level_code', 'section_name',
            'roll_number', 'admission_date', 'blood_group', 'nationality', 'previous_school_name',
            'emergency_contact_name', 'emergency_contact_phone', 'address_line1', 'city',
            'guardian1_first_name', 'guardian1_last_name', 'guardian1_email', 'guardian1_phone',
            'guardian1_relationship', 'guardian1_is_primary', 'guardian1_can_pickup',
            'guardian2_first_name', 'guardian2_last_name', 'guardian2_email', 'guardian2_phone',
            'guardian2_relationship', 'guardian2_is_primary', 'guardian2_can_pickup',
        ];

        $rows = [[
            'Preview', 'Only', 'female', '2018-05-01', 'G1', 'A',
            '', now()->toDateString(), '', '', '',
            '', '', '', '',
            'John', 'Guardian', 'john.guardian@example.com', '+1-555-0101',
            'father', 'yes', 'yes',
            '', '', '', '', '', '', '',
        ]];

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
        }, 'student-dry-run-test.xlsx', 'local');

        $uploadedFile = new UploadedFile(
            Storage::disk('local')->path('student-dry-run-test.xlsx'),
            'student-dry-run-test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAsInSchool($admin)->post('/api/v1/students/import', [
            'file' => $uploadedFile,
            'dry_run' => true,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.imported_count'));
        $this->assertTrue($response->json('data.dry_run'));
        $this->assertDatabaseMissing('students', ['first_name' => 'Preview']);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('guardians', 0);
    }
}
