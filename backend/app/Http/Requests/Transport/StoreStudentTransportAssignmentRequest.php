<?php

namespace App\Http\Requests\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentTransportAssignmentRequest extends FormRequest
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
            'route_id' => ['required', Rule::exists('routes', 'id')->where('school_id', $schoolId)],
            'route_stop_id' => ['required', Rule::exists('route_stops', 'id')->where('school_id', $schoolId)],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('school_id', $schoolId)],
            'effective_from' => ['required', 'date'],
        ];
    }
}
