<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->words(2, true).' Certificate',
            'type' => fake()->randomElement(['Bonafide', 'Transfer', 'Character', 'Achievement']),
            'body' => 'This is to certify that {{student_name}} (Admission No. {{admission_number}}) is a bonafide student of {{school_name}} in {{grade_level}} - {{section}}. Issued on {{date}}.',
            'is_active' => true,
        ];
    }
}
