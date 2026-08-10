<?php

namespace App\Http\Requests\Certificates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'issued_date' => ['sometimes', 'date'],
        ];
    }
}
