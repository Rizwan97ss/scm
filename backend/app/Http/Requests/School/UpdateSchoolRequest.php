<?php

namespace App\Http\Requests\School;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', new ValidName],
            'short_name' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('schools', 'short_name')->ignore($this->route('school'))],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'string', 'max:100'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
