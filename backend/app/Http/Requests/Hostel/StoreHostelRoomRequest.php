<?php

namespace App\Http\Requests\Hostel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHostelRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'hostel_id' => ['required', Rule::exists('hostels', 'id')->where('school_id', $schoolId)],
            'room_number' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
