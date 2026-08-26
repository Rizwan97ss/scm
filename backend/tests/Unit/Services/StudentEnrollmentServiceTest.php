<?php

namespace Tests\Unit\Services;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEnrollmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_promote_updates_student_and_records_history(): void
    {
        $school = School::factory()->create();
        tenancy()->initialize($school);
        $performedBy = User::factory()->create();
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $nextYear = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $fromGradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $toGradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $fromSection = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $fromGradeLevel->id]);
        tenancy()->initialize($school);
        $toSection = Section::factory()->create(['academic_year_id' => $nextYear->id, 'grade_level_id' => $toGradeLevel->id]);

        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $fromGradeLevel->id,
            'current_section_id' => $fromSection->id,
            'status' => 'active',
        ]);

        $service = app(StudentEnrollmentService::class);
        $updated = $service->promote($student, $toGradeLevel, $toSection, $nextYear, $performedBy);

        $this->assertEquals($toGradeLevel->id, $updated->current_grade_level_id);
        $this->assertEquals($toSection->id, $updated->current_section_id);
        $this->assertEquals($nextYear->id, $updated->academic_year_id);
        $this->assertEquals(StudentStatus::Active, $updated->status);

        $history = $student->enrollmentHistories()->first();
        $this->assertEquals('promotion', $history->action->value);
        $this->assertEquals($fromGradeLevel->id, $history->from_grade_level_id);
        $this->assertEquals($toGradeLevel->id, $history->to_grade_level_id);
        $this->assertEquals($performedBy->id, $history->performed_by);
    }

    public function test_withdraw_clears_current_section_and_sets_status(): void
    {
        $school = School::factory()->create();
        tenancy()->initialize($school);
        $performedBy = User::factory()->create();
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id, 'status' => 'active',
        ]);

        $service = app(StudentEnrollmentService::class);
        $updated = $service->withdraw($student, $performedBy, 'Relocation');

        $this->assertEquals(StudentStatus::Withdrawn, $updated->status);
        $this->assertNull($updated->current_grade_level_id);
        $this->assertNull($updated->current_section_id);
    }

    public function test_graduate_sets_alumni_status(): void
    {
        $school = School::factory()->create();
        tenancy()->initialize($school);
        $performedBy = User::factory()->create();
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id, 'status' => 'active',
        ]);

        $service = app(StudentEnrollmentService::class);
        $updated = $service->graduate($student, $performedBy);

        $this->assertEquals(StudentStatus::Graduated, $updated->status);
    }
}
