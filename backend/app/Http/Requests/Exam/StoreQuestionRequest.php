<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'subject_id' => ['nullable', Rule::exists('subjects', 'id')],
            'exam_subject_id' => ['nullable', Rule::exists('exam_subjects', 'id')],
            'type' => ['required', Rule::in(['mcq', 'true_false'])],
            'text' => ['required', 'string'],
            'default_marks' => ['sometimes', 'numeric', 'min:0.01'],
            'negative_marks' => ['nullable', 'numeric', 'min:0'],
            'explanation' => ['nullable', 'string'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.option_text' => ['required', 'string', 'max:500'],
            'options.*.is_correct' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $options = $this->input('options', []);
            $correctCount = collect($options)->filter(fn ($o) => $o['is_correct'] ?? false)->count();

            if ($correctCount !== 1) {
                $validator->errors()->add('options', 'Exactly one option must be marked correct.');
            }

            if ($this->input('type') === 'true_false' && count($options) !== 2) {
                $validator->errors()->add('options', 'True/False questions must have exactly 2 options.');
            }
        });
    }
}
