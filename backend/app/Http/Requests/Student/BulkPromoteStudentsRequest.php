<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPromoteStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [Rule::exists('students', 'id')],
            'to_grade_level_id' => ['required', Rule::exists('grade_levels', 'id')],
            'to_section_id' => ['required', Rule::exists('sections', 'id')],
            'to_academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'reason' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}
