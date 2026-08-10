<?php

namespace App\Http\Requests\Hostel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHostelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('hostels', 'name')->ignore($this->route('hostel'))],
            'type' => ['sometimes', 'required', Rule::in(['boys', 'girls', 'mixed'])],
            'address' => ['nullable', 'string', 'max:255'],
            'warden_name' => ['nullable', 'string', 'max:255'],
            'warden_phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
