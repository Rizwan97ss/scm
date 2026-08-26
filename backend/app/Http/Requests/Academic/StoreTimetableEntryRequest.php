<?php

namespace App\Http\Requests\Academic;

use App\Models\TimetableEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'section_id' => ['required', Rule::exists('sections', 'id')],
            'subject_id' => ['nullable', Rule::exists('subjects', 'id')],
            'teacher_id' => ['nullable', Rule::exists('users', 'id')],
            'room_id' => ['nullable', Rule::exists('rooms', 'id')],
            'timetable_period_id' => ['required', Rule::exists('timetable_periods', 'id')],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('teacher_id')) {
                return;
            }

            $conflict = TimetableEntry::query()
                ->where('teacher_id', $this->input('teacher_id'))
                ->where('timetable_period_id', $this->input('timetable_period_id'))
                ->where('day_of_week', $this->input('day_of_week'))
                ->when($this->route('timetableEntry'), fn ($q, $id) => $q->whereKeyNot($id))
                ->exists();

            if ($conflict) {
                $validator->errors()->add('teacher_id', 'This teacher is already scheduled for another section in that period.');
            }
        });
    }
}
