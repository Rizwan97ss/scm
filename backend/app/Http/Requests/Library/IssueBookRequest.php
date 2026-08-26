<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            // Exactly one borrower — required_without covers "at least one",
            // prohibits covers "not both" (Book::isTaughtBy-style XOR, done
            // via rules rather than a DB constraint since that's not portable
            // across SQLite/MySQL for a "one of two nullable columns" check).
            'student_id' => ['required_without:user_id', 'prohibits:user_id', 'nullable', Rule::exists('students', 'id')],
            'user_id' => ['required_without:student_id', 'nullable', Rule::exists('users', 'id')],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
