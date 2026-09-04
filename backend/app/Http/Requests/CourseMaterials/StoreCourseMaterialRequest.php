<?php

namespace App\Http\Requests\CourseMaterials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', Rule::exists('sections', 'id')],
            'subject_id' => ['required', Rule::exists('subjects', 'id')],
            // Not ValidName — a resource title is a free-text headline, not an identifier.
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['document', 'link', 'video'])],
            'url' => ['required_if:type,link,video', 'nullable', 'url', 'max:2048'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
