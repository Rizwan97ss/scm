<?php

namespace Tests\Feature\Exams;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class GradingScaleTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_admin_can_create_a_grading_scale_with_bands(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/grading-scales', [
            'name' => 'Standard Percentage',
            'is_default' => true,
            'grade_bands' => [
                ['min_percentage' => 90, 'max_percentage' => 100, 'grade_label' => 'A+', 'grade_point' => 4.0, 'remark' => 'Excellent'],
                ['min_percentage' => 80, 'max_percentage' => 89.99, 'grade_label' => 'A', 'grade_point' => 3.7],
                ['min_percentage' => 0, 'max_percentage' => 79.99, 'grade_label' => 'B', 'grade_point' => 3.0],
            ],
        ]);

        $response->assertCreated()->assertJsonCount(3, 'data.grade_bands');
        $this->assertDatabaseHas('grading_scales', ['school_id' => $school->id, 'name' => 'Standard Percentage']);
        $this->assertDatabaseCount('grade_bands', 3);
    }

    public function test_min_percentage_cannot_exceed_max_percentage(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/grading-scales', [
            'name' => 'Bad Scale',
            'grade_bands' => [
                ['min_percentage' => 90, 'max_percentage' => 50, 'grade_label' => 'A'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('grade_bands.0.max_percentage');
    }

    public function test_updating_a_scale_replaces_its_bands_wholesale(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $createResponse = $this->actingAsInSchool($admin)->postJson('/api/v1/grading-scales', [
            'name' => 'Scale', 'grade_bands' => [['min_percentage' => 0, 'max_percentage' => 100, 'grade_label' => 'A']],
        ]);
        $scaleId = $createResponse->json('data.id');

        $updateResponse = $this->actingAsInSchool($admin)->putJson("/api/v1/grading-scales/{$scaleId}", [
            'name' => 'Scale', 'grade_bands' => [
                ['min_percentage' => 50, 'max_percentage' => 100, 'grade_label' => 'Pass'],
                ['min_percentage' => 0, 'max_percentage' => 49.99, 'grade_label' => 'Fail'],
            ],
        ]);

        $updateResponse->assertOk()->assertJsonCount(2, 'data.grade_bands');
        $this->assertDatabaseCount('grade_bands', 2);
    }

    public function test_teacher_cannot_manage_grading_scales(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/grading-scales', [
            'name' => 'Scale', 'grade_bands' => [['min_percentage' => 0, 'max_percentage' => 100, 'grade_label' => 'A']],
        ]);

        $response->assertStatus(403);
    }
}
