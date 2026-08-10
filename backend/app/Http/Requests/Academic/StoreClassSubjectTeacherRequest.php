<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassSubjectTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'section_id' => ['required', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'subject_id' => [
                'required',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId),
                Rule::unique('class_subject_teacher', 'subject_id')
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->where('section_id', $this->input('section_id')),
            ],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('school_id', $schoolId)],
        ];
    }
}
