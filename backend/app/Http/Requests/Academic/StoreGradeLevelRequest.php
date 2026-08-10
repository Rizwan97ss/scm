<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradeLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', Rule::unique('grade_levels', 'code')->where('school_id', $this->user()->school_id)],
            'sequence' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
