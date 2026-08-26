<?php

namespace Tests\Feature\Isolation;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * MediaController (app/Http/Controllers/Api/V1/MediaController.php) is the
 * single authenticated gate every uploaded file goes through — this had zero
 * test coverage of any kind before, cross-tenant or otherwise. Media rows
 * live in each tenant's own physical database (same as every other model
 * here), so the isolation itself is structural; this proves the polymorphic
 * Gate::authorize('view', $media->model) dispatch actually holds for a
 * real Student-owned file, not just that the route exists.
 */
class MediaIsolationTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('media-library.disk_name'));
    }

    private function makeStudentWithPhoto(\App\Models\School $school, string $firstName): Student
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        tenancy()->initialize($school);

        $student = Student::factory()->create([
            'first_name' => $firstName,
            'academic_year_id' => $year->id,
            'current_grade_level_id' => $gradeLevel->id,
            'current_section_id' => $section->id,
        ]);

        $student->addMedia(UploadedFile::fake()->image(strtolower($firstName).'.jpg'))->toMediaCollection('photo');

        return $student->fresh();
    }

    public function test_admin_can_fetch_their_own_students_photo(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $student = $this->makeStudentWithPhoto($school, 'Alpha');
        $media = $student->getFirstMedia('photo');

        $response = $this->actingAsInSchool($admin)->get("/api/v1/media/{$media->id}");

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    /**
     * Same primary key in both tenants' media tables (fresh auto-increment
     * per physical database) -- if isolation ever regressed to something
     * merely query-scoped, this exact shape (a same-ID row in a DIFFERENT
     * tenant's connection) is what would leak.
     */
    /**
     * Same shape as CrossTenantIsolationTest::test_same_primary_key_resolves_
     * to_each_tenants_own_row(): both schools' first media row lands on PK 1
     * (fresh auto-increment per tenant database), so this is NOT a 404 case —
     * from adminA's own tenant connection, media #1 unambiguously IS Alpha's
     * photo. A same-ID collision resolving to the wrong tenant's row would be
     * the leak; resolving to admin A's own row (never Beta's) is the proof
     * isolation is physical, not merely filtered.
     */
    public function test_same_media_id_resolves_to_each_tenants_own_photo(): void
    {
        $schoolA = $this->createSchool();
        $adminA = $this->createUserWithRole($schoolA, 'School Admin');
        $studentA = $this->makeStudentWithPhoto($schoolA, 'Alpha');
        $mediaA = $studentA->getFirstMedia('photo');

        $schoolB = $this->createSchool();
        $studentB = $this->makeStudentWithPhoto($schoolB, 'Beta');
        $mediaB = $studentB->getFirstMedia('photo');

        $this->assertSame($mediaA->id, $mediaB->id, 'test premise: both tenants must assign the same PK for this to prove anything');

        $response = $this->actingAsInSchool($adminA)->get("/api/v1/media/{$mediaB->id}");

        $response->assertOk();
        $this->assertStringContainsString($mediaA->file_name, $response->headers->get('Content-Disposition'));
    }

    /**
     * The genuine cross-tenant-guess case: adminA has no media of their own
     * at all, so any ID they guess simply doesn't exist as a row in their
     * own tenant's connection -- 404, not a leak of school B's row.
     */
    public function test_admin_cannot_fetch_another_schools_student_photo_by_guessing_the_media_id(): void
    {
        $schoolA = $this->createSchool();
        $adminA = $this->createUserWithRole($schoolA, 'School Admin');

        $schoolB = $this->createSchool();
        $studentB = $this->makeStudentWithPhoto($schoolB, 'Beta');
        $mediaB = $studentB->getFirstMedia('photo');

        $response = $this->actingAsInSchool($adminA)->get("/api/v1/media/{$mediaB->id}");

        $response->assertStatus(404);
    }

    public function test_a_teacher_without_view_access_to_the_student_cannot_fetch_their_photo(): void
    {
        $school = $this->createSchool();
        // A plain Teacher has no class-subject-teacher assignment to this
        // student's section at all, so StudentPolicy::view() must deny them.
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $student = $this->makeStudentWithPhoto($school, 'Alpha');
        $media = $student->getFirstMedia('photo');

        $response = $this->actingAsInSchool($teacher)->get("/api/v1/media/{$media->id}");

        $response->assertStatus(403);
    }
}
