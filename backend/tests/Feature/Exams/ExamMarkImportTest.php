<?php

namespace Tests\Feature\Exams;

use App\Models\AcademicYear;
use App\Models\ClassSubjectTeacher;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/** Mirrors QuestionImportTest's Excel::store-via-UploadedFile technique for building a real import fixture. */
class ExamMarkImportTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    /** @return array{0: ExamSubject, 1: Student, 2: Student, 3: Section} */
    private function makeMarkableComponent(School $school, ?int $teacherId = null): array
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        tenancy()->initialize($school);
        $subject = Subject::factory()->create();
        tenancy()->initialize($school);
        $exam = Exam::factory()->create(['academic_year_id' => $year->id]);
        tenancy()->initialize($school);
        $examSubject = ExamSubject::factory()->create([
            'exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id, 'max_marks' => 100,
        ]);

        if ($teacherId) {
            ClassSubjectTeacher::query()->create([
                'academic_year_id' => $year->id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherId,
            ]);
        }

        tenancy()->initialize($school);
        $studentA = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
            'admission_number' => '2026-1001',
        ]);
        tenancy()->initialize($school);
        $studentB = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
            'admission_number' => '2026-1002',
        ]);

        return [$examSubject, $studentA, $studentB, $section];
    }

    private function storeImportFile(array $rows, string $name = 'marks-import-test.xlsx'): UploadedFile
    {
        Excel::store(new class($rows) implements FromArray, WithHeadings
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['admission_number', 'marks_obtained', 'is_absent', 'remarks'];
            }
        }, $name, 'local');

        return new UploadedFile(
            Storage::disk('local')->path($name), $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true,
        );
    }

    public function test_admin_can_import_marks_with_a_partial_failure_report(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$examSubject, $studentA, $studentB] = $this->makeMarkableComponent($school);

        $rows = [
            ['2026-1001', 78, '', 'Good effort'],
            ['2026-1002', '', 'true', 'Medical leave'],
            ['2026-9999', 50, '', ''], // no such student in this section
            ['2026-1001', 60, '', ''], // duplicate admission number within this same file
        ];
        $file = $this->storeImportFile($rows);

        $response = $this->actingAsInSchool($admin)->post("/api/v1/exam-subjects/{$examSubject->id}/marks/import", [
            'file' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.imported_count'));
        $this->assertEquals(2, $response->json('data.failed_count'));
        $this->assertDatabaseHas('exam_marks', ['exam_subject_id' => $examSubject->id, 'student_id' => $studentA->id, 'marks_obtained' => 78]);
        $this->assertDatabaseHas('exam_marks', ['exam_subject_id' => $examSubject->id, 'student_id' => $studentB->id, 'marks_obtained' => null, 'is_absent' => true]);
    }

    public function test_marks_exceeding_the_components_max_marks_soft_fail(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$examSubject, $studentA] = $this->makeMarkableComponent($school); // max_marks = 100

        $file = $this->storeImportFile([['2026-1001', 150, '', '']]);

        $response = $this->actingAsInSchool($admin)->post("/api/v1/exam-subjects/{$examSubject->id}/marks/import", [
            'file' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported_count'));
        $this->assertEquals(1, $response->json('data.failed_count'));
        $this->assertDatabaseMissing('exam_marks', ['exam_subject_id' => $examSubject->id, 'student_id' => $studentA->id]);
    }

    public function test_re_importing_the_same_student_updates_instead_of_duplicating(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$examSubject, $studentA] = $this->makeMarkableComponent($school);

        $first = $this->storeImportFile([['2026-1001', 70, '', '']], 'first.xlsx');
        $this->actingAsInSchool($admin)->post("/api/v1/exam-subjects/{$examSubject->id}/marks/import", ['file' => $first], ['Accept' => 'application/json'])->assertOk();

        $second = $this->storeImportFile([['2026-1001', 85, '', '']], 'second.xlsx');
        $this->actingAsInSchool($admin)->post("/api/v1/exam-subjects/{$examSubject->id}/marks/import", ['file' => $second], ['Accept' => 'application/json'])->assertOk();

        tenancy()->initialize($school);
        $this->assertSame(1, \App\Models\ExamMark::query()->where('exam_subject_id', $examSubject->id)->where('student_id', $studentA->id)->count());
        $this->assertDatabaseHas('exam_marks', ['exam_subject_id' => $examSubject->id, 'student_id' => $studentA->id, 'marks_obtained' => 85]);
    }

    public function test_a_teacher_who_does_not_teach_the_subject_cannot_import_marks(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$examSubject] = $this->makeMarkableComponent($school); // no teacher assigned

        $file = $this->storeImportFile([['2026-1001', 70, '', '']]);

        $response = $this->actingAsInSchool($teacher)->post("/api/v1/exam-subjects/{$examSubject->id}/marks/import", [
            'file' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(403);
    }

    public function test_principal_cannot_import_marks_or_download_the_template(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        // Principal holds exam-marks.edit/.publish but never .enter/.import — see SchoolProvisioningService.
        $principal = $this->createUserWithRole($school, 'Principal');
        [$examSubject] = $this->makeMarkableComponent($school);

        $file = $this->storeImportFile([['2026-1001', 70, '', '']]);

        $this->actingAsInSchool($principal)->post("/api/v1/exam-subjects/{$examSubject->id}/marks/import", [
            'file' => $file,
        ], ['Accept' => 'application/json'])->assertStatus(403);

        $this->actingAsInSchool($principal)->get("/api/v1/exam-subjects/{$examSubject->id}/marks/import/template")->assertStatus(403);
    }
}
