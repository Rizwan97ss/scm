<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'max:150'],
            'settings.*.value' => ['nullable'],
            'settings.*.type' => ['required', Rule::in(['string', 'integer', 'boolean', 'float', 'json', 'array'])],
            'settings.*.group' => ['required', 'string', 'max:50'],
            'settings.*.is_public' => ['sometimes', 'boolean'],
        ];
    }
}
