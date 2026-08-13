<?php

namespace App\Http\Requests\NoticeBoard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Not ValidName — a notice title is a free-text headline
            // ("Reminder: Field trip tomorrow!"), not an identifier.
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'type' => ['sometimes', Rule::in(['general', 'event'])],
            'audience' => ['sometimes', Rule::in(['all', 'students', 'staff', 'parents'])],
            'event_date' => ['nullable', 'date', 'required_if:type,event'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
