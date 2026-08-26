<?php

namespace App\Http\Requests\Hostel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHostelRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_number' => ['sometimes', 'required', 'string', 'max:50'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
