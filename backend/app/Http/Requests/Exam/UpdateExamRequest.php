<?php

namespace App\Http\Requests\Exam;

class UpdateExamRequest extends StoreExamRequest
{
    // Same shape as Store — exam_subject_groups/components are upserted,
    // never replaced wholesale (see ExamController::update()).
}
