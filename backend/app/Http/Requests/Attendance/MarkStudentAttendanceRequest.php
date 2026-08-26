<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MarkStudentAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'section_id' => ['required', Rule::exists('sections', 'id')],
            'date' => ['required', 'date'],
            'timetable_period_id' => ['nullable', Rule::exists('timetable_periods', 'id')],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', Rule::exists('students', 'id')],
            'entries.*.status' => ['required', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
            'entries.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Rule::exists() alone can't cross-check that each student is actually
     * enrolled in the section attendance is being taken for — a student ID
     * valid for the school but belonging to a different section would
     * otherwise silently write a wrong section_id onto the attendance row.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sectionId = $this->integer('section_id');
            $studentIds = collect($this->input('entries', []))->pluck('student_id')->filter()->all();

            if (! $sectionId || empty($studentIds)) {
                return;
            }

            $validCount = Student::query()
                ->whereIn('id', $studentIds)
                ->where('current_section_id', $sectionId)
                ->count();

            if ($validCount !== count($studentIds)) {
                $validator->errors()->add('entries', 'One or more students are not currently enrolled in the selected section.');
            }
        });
    }
}
