<?php

namespace App\Enums;

enum PayslipStatus: string
{
    case Generated = 'generated';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Generated',
            self::Paid => 'Paid',
        };
    }
}
