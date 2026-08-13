<?php

namespace App\Http\Requests\Certificates;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templateId = $this->route('certificate_template');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150', new ValidName, Rule::unique('certificate_templates', 'name')->ignore($templateId)],
            'type' => ['sometimes', 'required', 'string', 'max:100'],
            'body' => ['sometimes', 'required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
