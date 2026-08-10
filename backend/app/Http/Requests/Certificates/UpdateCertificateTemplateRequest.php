<?php

namespace App\Http\Requests\Certificates;

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
        $schoolId = $this->user()->school_id;
        $templateId = $this->route('certificate_template');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('certificate_templates', 'name')->where('school_id', $schoolId)->ignore($templateId)],
            'type' => ['sometimes', 'required', 'string', 'max:100'],
            'body' => ['sometimes', 'required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
