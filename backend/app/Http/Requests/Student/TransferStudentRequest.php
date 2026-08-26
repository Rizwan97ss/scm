<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class TransferStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}
