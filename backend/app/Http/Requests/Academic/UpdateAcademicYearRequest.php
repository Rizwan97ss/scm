<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('academic_years', 'name')->where('school_id', $this->user()->school_id)->ignore($this->route('academicYear')),
            ],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['upcoming', 'active', 'closed'])],
        ];
    }
}
