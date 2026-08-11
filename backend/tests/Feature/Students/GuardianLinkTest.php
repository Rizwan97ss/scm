<?php

namespace Tests\Feature\Students;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class GuardianLinkTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function student($school): Student
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);

        tenancy()->initialize($school);
        return Student::factory()->create([
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
        ]);
    }

    public function test_admin_can_link_an_existing_guardian_to_a_student(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $student = $this->student($school);
        tenancy()->initialize($school);
        $guardian = Guardian::factory()->create();

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/students/{$student->id}/guardians", [
            'guardian_id' => $guardian->id,
            'relationship_type' => 'father',
            'is_primary' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('student_guardian', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'father',
        ]);
    }

    public function test_admin_can_detach_a_guardian_from_a_student(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $student = $this->student($school);
        tenancy()->initialize($school);
        $guardian = Guardian::factory()->create();
        $student->guardians()->attach($guardian->id, ['relationship_type' => 'mother', 'is_primary' => true]);

        $response = $this->actingAsInSchool($admin)->deleteJson("/api/v1/students/{$student->id}/guardians/{$guardian->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('student_guardian', ['student_id' => $student->id, 'guardian_id' => $guardian->id]);
    }

    public function test_guardian_invite_sends_reset_link_and_grants_parent_role(): void
    {
        Notification::fake();

        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        tenancy()->initialize($school);
        $guardian = Guardian::factory()->create(['email' => 'parent@example.com', 'user_id' => null]);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/guardians/{$guardian->id}/invite");

        $response->assertOk();
        $guardian->refresh();
        $this->assertNotNull($guardian->user_id);
        $this->assertTrue($guardian->user->hasRole('Parent'));
        $this->assertNotNull($guardian->invited_at);
    }

    public function test_parent_only_sees_their_own_linked_children(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $parentUser = $this->createUserWithRole($school, 'Parent');
        tenancy()->initialize($school);
        $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);

        $myChild = $this->student($school);
        $someoneElsesChild = $this->student($school);
        $guardian->students()->attach($myChild->id, ['relationship_type' => 'mother', 'is_primary' => true]);

        $response = $this->actingAsInSchool($parentUser)->getJson('/api/v1/parent/children');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($myChild->id));
        $this->assertFalse($ids->contains($someoneElsesChild->id));
    }
}
