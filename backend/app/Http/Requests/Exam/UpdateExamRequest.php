<?php

namespace App\Http\Requests\Exam;

class UpdateExamRequest extends StoreExamRequest
{
    // Same shape as Store — exam_subjects are replaced wholesale on update
    // (see ExamController::update()).
}
