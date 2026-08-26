<?php

namespace Database\Factories;

use App\Models\AssessmentComponentType;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ExamSubjectGroup;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamSubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'subject_id' => Subject::factory(),
            'section_id' => Section::factory(),
            'assessment_component_type_id' => AssessmentComponentType::factory(),
            'sequence' => 0,
            'max_marks' => 100,
        ];
    }

    /**
     * Auto-creates (or reuses) the ExamSubjectGroup this component belongs
     * under, matching whatever exam_id/subject_id/section_id this factory
     * call ends up with — including test overrides passed to create(), since
     * afterMaking() runs once those are already merged in. firstOrCreate
     * keeps a second component for the same (exam, subject, section) — e.g.
     * a test building Online MCQ + Written for one subject — under the same
     * group instead of violating exam_subject_groups' own unique key.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ExamSubject $examSubject) {
            if (! $examSubject->exam_subject_group_id) {
                $group = ExamSubjectGroup::query()->firstOrCreate([
                    'exam_id' => $examSubject->exam_id,
                    'subject_id' => $examSubject->subject_id,
                    'section_id' => $examSubject->section_id,
                ]);

                $examSubject->exam_subject_group_id = $group->id;
            }
        });
    }
}
