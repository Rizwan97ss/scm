<?php

namespace App\Http\Requests\FrontDesk;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new ValidName],
            'phone' => ['nullable', 'string', 'max:30'],
            'purpose' => ['required', 'string', 'max:255'],
            'whom_to_meet' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
