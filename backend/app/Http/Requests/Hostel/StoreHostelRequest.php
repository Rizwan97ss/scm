<?php

namespace App\Http\Requests\Hostel;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHostelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => ['required', 'string', 'max:150', new ValidName, Rule::unique('hostels', 'name')],
            'type' => ['required', Rule::in(['boys', 'girls', 'mixed'])],
            'address' => ['nullable', 'string', 'max:255'],
            'warden_name' => ['nullable', 'string', 'max:255'],
            'warden_phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
