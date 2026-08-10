<?php

namespace App\Http\Requests\Homework;

use Illuminate\Foundation\Http\FormRequest;

class SubmitHomeworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ];
    }
}
