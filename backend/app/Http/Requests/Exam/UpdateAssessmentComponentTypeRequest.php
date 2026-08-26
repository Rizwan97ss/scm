<?php

namespace App\Http\Requests\Exam;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentComponentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $componentType = $this->route('assessment_component_type');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', new ValidName],
            'code' => ['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('assessment_component_types', 'code')->ignore($componentType)],
            'is_auto_graded' => ['sometimes', 'boolean'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
