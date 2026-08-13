<?php

namespace Tests\Feature\Exams;

use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/** Mirrors StudentImportExportTest's UploadedFile-via-Excel::store technique for building a real import fixture. */
class QuestionImportTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function storeImportFile(array $rows, string $name = 'question-import-test.xlsx'): UploadedFile
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
                return ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'marks', 'negative_marks'];
            }
        }, $name, 'local');

        return new UploadedFile(
            Storage::disk('local')->path($name), $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true,
        );
    }

    public function test_admin_can_import_mcq_questions_with_a_partial_failure_report(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $subject = Subject::factory()->create();

        $rows = [
            ['What is 2+2?', '3', '4', '5', '6', 'B', 2, 0.5],
            ['Duplicate question', 'True', 'False', '', '', 'A', 1, 0],
            ['Duplicate question', 'True', 'False', '', '', 'A', 1, 0], // duplicate of the row above, within the same file
            ['Bad correct option', 'True', 'False', '', '', 'C', 1, 0], // 'C' isn't a filled option on this row
        ];
        $file = $this->storeImportFile($rows);

        $response = $this->actingAsInSchool($admin)->post('/api/v1/questions/import', [
            'file' => $file, 'subject_id' => $subject->id,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.imported_count'));
        $this->assertEquals(2, $response->json('data.failed_count'));
        $this->assertDatabaseHas('questions', ['text' => 'What is 2+2?', 'type' => 'mcq', 'subject_id' => $subject->id, 'negative_marks' => 0.5]);
        $this->assertDatabaseHas('question_options', ['option_text' => '4', 'is_correct' => true]);
        // True/false auto-detection: exactly two filled options, both literally "true"/"false".
        $this->assertDatabaseHas('questions', ['text' => 'Duplicate question', 'type' => 'true_false']);
        $this->assertSame(1, \App\Models\Question::query()->where('text', 'Duplicate question')->count());
        $this->assertDatabaseMissing('questions', ['text' => 'Bad correct option']);
    }

    public function test_a_role_without_the_import_permission_cannot_import_questions(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        // Principal holds questions.view/questions.edit but not questions.import — see SchoolProvisioningService.
        $principal = $this->createUserWithRole($school, 'Principal');
        tenancy()->initialize($school);
        $subject = Subject::factory()->create();
        $file = $this->storeImportFile([['Sample question?', 'A', 'B', '', '', 'A', 1, 0]]);

        $response = $this->actingAsInSchool($principal)->post('/api/v1/questions/import', [
            'file' => $file, 'subject_id' => $subject->id,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(403);
    }

    public function test_a_role_without_the_import_permission_cannot_download_the_template(): void
    {
        $school = $this->createSchool();
        $principal = $this->createUserWithRole($school, 'Principal');

        $response = $this->actingAsInSchool($principal)->get('/api/v1/questions/import/template');

        $response->assertStatus(403);
    }
}
