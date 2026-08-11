<?php

namespace Tests\Feature\Isolation;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * The actual point of the database-per-tenant conversion: proving PHYSICAL
 * isolation, not just query-scoping. Under the old row-level architecture,
 * every one of these was a `school_id` filter that a bug (a missing
 * ->where(), a forgotten global scope) could silently drop. Under
 * database-per-tenant, there is no `school_id` column left to drop a filter
 * on — a query issued against one tenant's connection is structurally
 * incapable of returning another tenant's rows, because that data lives in
 * a different physical database file.
 */
class CrossTenantIsolationTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeStudent(\App\Models\School $school, array $attributes = []): Student
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        tenancy()->initialize($school);

        return Student::factory()->create(array_merge([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
        ], $attributes));
    }

    /**
     * Both schools' first student lands on primary key 1 — each tenant's
     * auto-increment sequence starts fresh in its own database. If any
     * cross-tenant leak were possible, this is exactly the shape it would
     * take: adminA's session incorrectly reaching a row that merely shares
     * an ID with one of their own.
     */
    public function test_same_primary_key_resolves_to_each_tenants_own_row(): void
    {
        $schoolA = $this->createSchool();
        $adminA = $this->createUserWithRole($schoolA, 'School Admin');
        $studentA = $this->makeStudent($schoolA, ['first_name' => 'Alpha']);

        $schoolB = $this->createSchool();
        $this->createUserWithRole($schoolB, 'School Admin');
        $studentB = $this->makeStudent($schoolB, ['first_name' => 'Beta']);

        $this->assertSame($studentA->id, $studentB->id, 'test premise: both tenants must assign the same PK for this to prove anything');

        $response = $this->actingAsInSchool($adminA)->getJson("/api/v1/students/{$studentA->id}");

        $response->assertOk()->assertJsonPath('data.first_name', 'Alpha');
    }

    /**
     * A guessed/crafted ID that happens to be valid in tenant B is simply
     * not a row that exists from tenant A's connection at all -- 404, the
     * same response a nonexistent ID would get. There's no "belongs to
     * another school" 403/leak to distinguish it by.
     */
    public function test_admin_cannot_reach_another_tenants_student_by_guessing_its_id(): void
    {
        $schoolA = $this->createSchool();
        $adminA = $this->createUserWithRole($schoolA, 'School Admin');

        $schoolB = $this->createSchool();
        $studentB = $this->makeStudent($schoolB);

        $response = $this->actingAsInSchool($adminA)->getJson("/api/v1/students/{$studentB->id}");

        $response->assertStatus(404);
    }

    /**
     * The isolation is physical, not just filtered: from tenant A's own
     * connection, tenant B's row is not merely hidden by a WHERE clause --
     * it does not exist in the table being queried at all, in either
     * direction.
     */
    public function test_tenant_connections_never_see_each_others_rows_at_the_database_level(): void
    {
        $schoolA = $this->createSchool();
        $studentA = $this->makeStudent($schoolA, ['first_name' => 'Alpha']);

        $schoolB = $this->createSchool();
        $studentB = $this->makeStudent($schoolB, ['first_name' => 'Beta']);

        $schoolA->run(function () use ($studentB) {
            $this->assertSame(1, Student::query()->count());
            $this->assertNull(Student::query()->where('first_name', 'Beta')->first());
            // Same PK, but this connection's row is Alpha's, never Beta's.
            $this->assertNotEquals('Beta', Student::query()->find($studentB->id)?->first_name);
        });

        $schoolB->run(function () use ($studentA) {
            $this->assertSame(1, Student::query()->count());
            $this->assertNull(Student::query()->where('first_name', 'Alpha')->first());
            $this->assertNotEquals('Alpha', Student::query()->find($studentA->id)?->first_name);
        });
    }
}
