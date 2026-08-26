<?php

namespace App\Http\Requests\Transport;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;

class StoreRouteStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', new ValidName],
            'sequence' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
