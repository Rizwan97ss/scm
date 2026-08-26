<?php

namespace App\Http\Requests\Exam;

use App\Rules\ValidName;
use Illuminate\Validation\Rule;

class UpdateGradingScaleRequest extends StoreGradingScaleRequest
{
    // Bands are always replaced wholesale on update (see
    // GradingScaleController::update()), so the same "full payload required"
    // rules from Store apply unchanged — only the uniqueness check needs to
    // ignore this scale's own row.
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['required', 'string', 'max:150', new ValidName, Rule::unique('grading_scales', 'name')->ignore($this->route('grading_scale'))],
        ];
    }
}
