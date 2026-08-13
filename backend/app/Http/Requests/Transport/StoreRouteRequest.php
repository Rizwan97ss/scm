<?php

namespace App\Http\Requests\Transport;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => ['required', 'string', 'max:150', new ValidName, Rule::unique('routes', 'name')],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'stops' => ['sometimes', 'array'],
            'stops.*.name' => ['required_with:stops', 'string', 'max:150'],
            'stops.*.sequence' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
