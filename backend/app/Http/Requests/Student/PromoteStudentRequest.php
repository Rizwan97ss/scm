<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'to_grade_level_id' => ['required', Rule::exists('grade_levels', 'id')],
            'to_section_id' => ['required', Rule::exists('sections', 'id')],
            'to_academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'reason' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}
