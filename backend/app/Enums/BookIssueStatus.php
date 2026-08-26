<?php

namespace App\Enums;

enum BookIssueStatus: string
{
    case Issued = 'issued';
    case Returned = 'returned';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Returned => 'Returned',
            self::Overdue => 'Overdue',
        };
    }
}
