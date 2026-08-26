<?php

namespace App\Enums;

enum HomeworkSubmissionStatus: string
{
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Graded => 'Graded',
        };
    }
}
