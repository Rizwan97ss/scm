<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'certificate_template_id' => CertificateTemplate::factory(),
            'certificate_number' => 'CERT-'.fake()->unique()->numerify('####'),
            'issued_date' => now()->toDateString(),
            'issued_by' => User::factory(),
            'content' => fake()->paragraph(),
        ];
    }
}
