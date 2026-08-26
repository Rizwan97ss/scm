<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Not ValidName — an announcement title is a free-text headline, not an identifier.
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'audience' => ['required', Rule::in(['all', 'students', 'staff', 'parents'])],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(['in_app', 'email', 'sms', 'push'])],
        ];
    }
}
