<?php

namespace App\Http\Requests\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'registration_number' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('vehicles', 'registration_number')->ignore($this->route('vehicle'))],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
