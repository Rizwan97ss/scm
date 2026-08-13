<?php

namespace App\Http\Requests\Academic;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'grade_level_id' => ['required', Rule::exists('grade_levels', 'id')],
            'name' => [
                'required', 'string', 'max:20', new ValidName,
                Rule::unique('sections', 'name')
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->where('grade_level_id', $this->input('grade_level_id')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'class_teacher_id' => ['nullable', Rule::exists('users', 'id')],
            'room_id' => ['nullable', Rule::exists('rooms', 'id')],
        ];
    }
}
