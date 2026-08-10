<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkStaffAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'date' => ['required', 'date'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.user_id' => ['required', Rule::exists('users', 'id')->where('school_id', $schoolId)],
            'entries.*.status' => ['required', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
            'entries.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
