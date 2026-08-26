<?php

namespace App\Http\Requests\Hr;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => ['required', 'string', 'max:100', new ValidName, Rule::unique('leave_types', 'name')],
            'days_allowed_per_year' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_paid' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
