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

        return [
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'section_id' => ['required', Rule::exists('sections', 'id')],
            'subject_id' => [
                'required',
                Rule::exists('subjects', 'id'),
                Rule::unique('class_subject_teacher', 'subject_id')
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->where('section_id', $this->input('section_id')),
            ],
            'teacher_id' => ['required', Rule::exists('users', 'id')],
        ];
    }
}
