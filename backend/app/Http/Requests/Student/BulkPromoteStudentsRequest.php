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
        $schoolId = $this->user()->school_id;

        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'to_grade_level_id' => ['required', Rule::exists('grade_levels', 'id')->where('school_id', $schoolId)],
            'to_section_id' => ['required', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'to_academic_year_id' => ['required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'reason' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}
