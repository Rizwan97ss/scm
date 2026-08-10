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
        $schoolId = $this->user()->school_id;

        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'hostel_room_id' => ['required', Rule::exists('hostel_rooms', 'id')->where('school_id', $schoolId)],
            'bed_number' => ['nullable', 'string', 'max:20'],
            'allocated_date' => ['required', 'date'],
        ];
    }
}
