<?php

namespace App\Http\Requests\Exam;

use App\Models\ExamSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MarkExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', Rule::exists('students', 'id')],
            'entries.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'entries.*.is_absent' => ['sometimes', 'boolean'],
            'entries.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * max_marks is per-ExamSubject (route-bound), not knowable as a static
     * rule — checked here instead of via a `max:` rule.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ExamSubject|null $examSubject */
            $examSubject = $this->route('examSubject');

            if (! $examSubject) {
                return;
            }

            foreach ($this->input('entries', []) as $index => $entry) {
                $marks = $entry['marks_obtained'] ?? null;

                if ($marks !== null && $marks > $examSubject->max_marks) {
                    $validator->errors()->add("entries.{$index}.marks_obtained", "Cannot exceed the maximum of {$examSubject->max_marks}.");
                }
            }
        });
    }
}
