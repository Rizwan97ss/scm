<?php

namespace App\Http\Requests\Exam;

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
            'name' => ['required', 'string', 'max:150'],
            'weight' => ['sometimes', 'numeric', 'min:0.01', 'max:100'],

            'exam_subjects' => ['sometimes', 'array'],
            'exam_subjects.*.subject_id' => ['required_with:exam_subjects', Rule::exists('subjects', 'id')],
            'exam_subjects.*.section_id' => ['required_with:exam_subjects', Rule::exists('sections', 'id')],
            'exam_subjects.*.grading_scale_id' => ['nullable', Rule::exists('grading_scales', 'id')],
            'exam_subjects.*.max_marks' => ['required_with:exam_subjects', 'numeric', 'min:1'],
            'exam_subjects.*.passing_marks' => ['nullable', 'numeric', 'min:0'],
            'exam_subjects.*.exam_date' => ['nullable', 'date'],
            'exam_subjects.*.is_online' => ['sometimes', 'boolean'],
            'exam_subjects.*.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'exam_subjects.*.online_starts_at' => ['nullable', 'date'],
            'exam_subjects.*.online_ends_at' => ['nullable', 'date', 'after:exam_subjects.*.online_starts_at'],
            'exam_subjects.*.shuffle_questions' => ['sometimes', 'boolean'],
            'exam_subjects.*.max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
