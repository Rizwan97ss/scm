<?php

namespace App\Http\Requests\Certificates;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => ['required', 'string', 'max:150', new ValidName, Rule::unique('certificate_templates', 'name')],
            'type' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string'],
            'layout' => ['sometimes', Rule::in(['classic', 'recognition', 'achievement', 'merit'])],
            'signatories' => ['sometimes', 'nullable', 'array', 'max:2'],
            'signatories.*.name' => ['required_with:signatories', 'string', 'max:100'],
            'signatories.*.title' => ['required_with:signatories', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
