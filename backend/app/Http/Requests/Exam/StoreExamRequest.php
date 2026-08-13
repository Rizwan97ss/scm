<?php

namespace App\Http\Requests\Exam;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'term_id' => ['nullable', Rule::exists('terms', 'id')],
            'exam_type_id' => ['nullable', Rule::exists('exam_types', 'id')],
            'name' => ['required', 'string', 'max:150', new ValidName],
            'weight' => ['sometimes', 'numeric', 'min:0.01', 'max:100'],

            'exam_subject_groups' => ['sometimes', 'array'],
            'exam_subject_groups.*.subject_id' => ['required_with:exam_subject_groups', Rule::exists('subjects', 'id')],
            'exam_subject_groups.*.section_id' => ['required_with:exam_subject_groups', Rule::exists('sections', 'id')],
            'exam_subject_groups.*.grading_scale_id' => ['nullable', Rule::exists('grading_scales', 'id')],
            'exam_subject_groups.*.passing_marks' => ['nullable', 'numeric', 'min:0'],

            'exam_subject_groups.*.components' => ['required_with:exam_subject_groups', 'array', 'min:1'],
            'exam_subject_groups.*.components.*.assessment_component_type_id' => ['required', Rule::exists('assessment_component_types', 'id')],
            'exam_subject_groups.*.components.*.max_marks' => ['required', 'numeric', 'min:1'],
            'exam_subject_groups.*.components.*.sequence' => ['nullable', 'integer', 'min:0'],
            'exam_subject_groups.*.components.*.exam_date' => ['nullable', 'date'],
            'exam_subject_groups.*.components.*.is_online' => ['sometimes', 'boolean'],
            'exam_subject_groups.*.components.*.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'exam_subject_groups.*.components.*.online_starts_at' => ['nullable', 'date'],
            'exam_subject_groups.*.components.*.online_ends_at' => ['nullable', 'date', 'after:exam_subject_groups.*.components.*.online_starts_at'],
            'exam_subject_groups.*.components.*.shuffle_questions' => ['sometimes', 'boolean'],
            'exam_subject_groups.*.components.*.max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
