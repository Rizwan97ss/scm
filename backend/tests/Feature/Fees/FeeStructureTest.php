<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class FeeStructureTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeSectionWithStudents(School $school, int $count = 2): array
    {
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);

        $students = Student::factory()->for($school)->count($count)->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id, 'status' => 'active',
        ]);

        return [$year, $gradeLevel, $section, $students];
    }

    public function test_accountant_can_create_a_fee_structure(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        $year = AcademicYear::factory()->for($school)->create();
        $category = FeeCategory::factory()->for($school)->create();

        $response = $this->actingAsInSchool($accountant)->postJson('/api/v1/fee-structures', [
            'academic_year_id' => $year->id,
            'fee_category_id' => $category->id,
            'name' => 'Grade 5 Tuition',
            'amount' => 4000,
            'frequency' => 'annual',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('fee_structures', ['school_id' => $school->id, 'name' => 'Grade 5 Tuition', 'amount' => 4000]);
    }

    public function test_generating_invoices_bulk_creates_one_per_active_student_in_the_section(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        [$year, $gradeLevel, $section, $students] = $this->makeSectionWithStudents($school, 3);
        $category = FeeCategory::factory()->for($school)->create();
        $structure = FeeStructure::factory()->for($school)->create([
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'fee_category_id' => $category->id, 'amount' => 1000,
        ]);

        $response = $this->actingAsInSchool($accountant)->postJson("/api/v1/fee-structures/{$structure->id}/generate-invoices", [
            'section_id' => $section->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.created_count', 3);
        $this->assertSame(3, Invoice::query()->where('school_id', $school->id)->count());
    }

    public function test_generating_invoices_twice_skips_already_invoiced_students(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        [$year, $gradeLevel, $section] = $this->makeSectionWithStudents($school, 2);
        $category = FeeCategory::factory()->for($school)->create();
        $structure = FeeStructure::factory()->for($school)->create([
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'fee_category_id' => $category->id, 'amount' => 1000,
        ]);

        $payload = ['section_id' => $section->id, 'issue_date' => now()->toDateString(), 'due_date' => now()->addWeek()->toDateString()];

        $this->actingAsInSchool($accountant)->postJson("/api/v1/fee-structures/{$structure->id}/generate-invoices", $payload)
            ->assertJsonPath('data.created_count', 2);

        $response = $this->actingAsInSchool($accountant)->postJson("/api/v1/fee-structures/{$structure->id}/generate-invoices", $payload);

        $response->assertJsonPath('data.created_count', 0);
        $response->assertJsonPath('data.skipped_count', 2);
        $this->assertSame(2, Invoice::query()->where('school_id', $school->id)->count());
    }

    public function test_generating_invoices_applies_a_students_fee_discount(): void
    {
        $school = $this->createSchool();
        $accountant = $this->createUserWithRole($school, 'Accountant');
        [$year, $gradeLevel, $section, $students] = $this->makeSectionWithStudents($school, 1);
        $category = FeeCategory::factory()->for($school)->create();
        $structure = FeeStructure::factory()->for($school)->create([
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'fee_category_id' => $category->id, 'amount' => 1000,
        ]);

        StudentFeeAssignment::factory()->for($school)->create([
            'student_id' => $students->first()->id,
            'fee_structure_id' => $structure->id,
            'discount_type' => 'percentage',
            'discount_value' => 25,
        ]);

        $this->actingAsInSchool($accountant)->postJson("/api/v1/fee-structures/{$structure->id}/generate-invoices", [
            'section_id' => $section->id, 'issue_date' => now()->toDateString(), 'due_date' => now()->addWeek()->toDateString(),
        ])->assertOk();

        $invoice = Invoice::query()->where('student_id', $students->first()->id)->firstOrFail();
        $this->assertSame(750.0, $invoice->total);
        $this->assertSame(250.0, $invoice->discount_total);
    }
}
