<?php

namespace App\Http\Requests\Library;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Not ValidName — real book titles legitimately use colons,
            // exclamation/question marks etc. ("Oh, the Places You'll Go!"),
            // unlike the identifier-style name fields ValidName targets.
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255', new ValidName],
            'isbn' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'total_copies' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
