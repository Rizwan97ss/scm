<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamSubjectGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'subject_id' => Subject::factory(),
            'section_id' => Section::factory(),
        ];
    }
}
