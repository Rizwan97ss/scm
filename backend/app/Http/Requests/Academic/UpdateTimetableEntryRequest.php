<?php

namespace App\Http\Requests\Academic;

use App\Models\TimetableEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'subject_id' => ['nullable', Rule::exists('subjects', 'id')],
            'teacher_id' => ['nullable', Rule::exists('users', 'id')],
            'room_id' => ['nullable', Rule::exists('rooms', 'id')],
            'timetable_period_id' => ['sometimes', 'required', Rule::exists('timetable_periods', 'id')],
            'day_of_week' => ['sometimes', 'required', 'integer', 'between:0,6'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var TimetableEntry $entry */
            $entry = $this->route('timetableEntry');

            $teacherId = $this->input('teacher_id', $entry->teacher_id);

            if (! $teacherId) {
                return;
            }

            $conflict = TimetableEntry::query()
                ->where('teacher_id', $teacherId)
                ->where('timetable_period_id', $this->input('timetable_period_id', $entry->timetable_period_id))
                ->where('day_of_week', $this->input('day_of_week', $entry->day_of_week))
                ->whereKeyNot($entry->id)
                ->exists();

            if ($conflict) {
                $validator->errors()->add('teacher_id', 'This teacher is already scheduled for another section in that period.');
            }
        });
    }
}
