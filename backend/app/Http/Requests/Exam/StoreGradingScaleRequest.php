<?php

namespace App\Http\Requests\Exam;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGradingScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', new ValidName, Rule::unique('grading_scales', 'name')],
            'is_default' => ['sometimes', 'boolean'],
            'grade_bands' => ['required', 'array', 'min:1'],
            'grade_bands.*.min_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'grade_bands.*.max_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'grade_bands.*.grade_label' => ['required', 'string', 'max:20'],
            'grade_bands.*.grade_point' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'grade_bands.*.remark' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('grade_bands', []) as $index => $band) {
                if (isset($band['min_percentage'], $band['max_percentage']) && $band['min_percentage'] > $band['max_percentage']) {
                    $validator->errors()->add("grade_bands.{$index}.max_percentage", 'Must be greater than or equal to the minimum percentage.');
                }
            }
        });
    }
}
