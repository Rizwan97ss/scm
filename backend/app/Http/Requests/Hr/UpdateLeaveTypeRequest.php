<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_types', 'name')->where('school_id', $schoolId)->ignore($this->route('leave_type'))],
            'days_allowed_per_year' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_paid' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
