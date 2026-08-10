<?php

namespace App\Http\Requests\Hostel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHostelAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'student_id' => ['required', Rule::exists('students', 'id')],
            'hostel_room_id' => ['required', Rule::exists('hostel_rooms', 'id')],
            'bed_number' => ['nullable', 'string', 'max:20'],
            'allocated_date' => ['required', 'date'],
        ];
    }
}
