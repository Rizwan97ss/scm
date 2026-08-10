<?php

namespace App\Http\Requests\Exam;

class UpdateQuestionRequest extends StoreQuestionRequest
{
    // Options are always replaced wholesale on update (see QuestionController::update()).
}
