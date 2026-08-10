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

        return [
            'student_id' => ['required', Rule::exists('students', 'id')],
            'route_id' => ['required', Rule::exists('routes', 'id')],
            'route_stop_id' => ['required', Rule::exists('route_stops', 'id')],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')],
            'effective_from' => ['required', 'date'],
        ];
    }
}
