<?php

namespace Tests\Unit\Services;

use App\Models\School;
use App\Services\IdSequenceService;
use App\Services\SettingsService;
use App\Services\StudentIdGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentIdGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_sequential_padded_admission_numbers_per_year(): void
    {
        $school = School::factory()->create(['short_name' => 'demo']);
        $service = new StudentIdGeneratorService(app(SettingsService::class), app(IdSequenceService::class));

        $first = $service->generate($school, \Carbon\Carbon::create(2026, 1, 1));
        $second = $service->generate($school, \Carbon\Carbon::create(2026, 6, 1));
        $thirdDifferentYear = $service->generate($school, \Carbon\Carbon::create(2027, 1, 1));

        $this->assertEquals('2026-0001', $first);
        $this->assertEquals('2026-0002', $second);
        $this->assertEquals('2027-0001', $thirdDifferentYear);
    }

    public function test_respects_custom_format_and_padding_settings(): void
    {
        $school = School::factory()->create(['short_name' => 'riv']);
        $settings = app(SettingsService::class);
        $settings->set('students.admission_number_format', '{SCHOOL}-{YEAR}-{SEQ}', $school->id);
        $settings->set('students.admission_number_padding', 3, $school->id);

        $service = new StudentIdGeneratorService($settings, app(IdSequenceService::class));

        $admissionNumber = $service->generate($school, \Carbon\Carbon::create(2026, 1, 1));

        $this->assertEquals('riv-2026-001', $admissionNumber);
    }

    public function test_sequences_are_isolated_per_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $service = new StudentIdGeneratorService(app(SettingsService::class), app(IdSequenceService::class));

        $service->generate($schoolA, \Carbon\Carbon::create(2026, 1, 1));
        $firstForB = $service->generate($schoolB, \Carbon\Carbon::create(2026, 1, 1));

        $this->assertEquals('2026-0001', $firstForB);
    }
}
