<?php

namespace App\Enums;

enum HostelType: string
{
    case Boys = 'boys';
    case Girls = 'girls';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Boys => 'Boys',
            self::Girls => 'Girls',
            self::Mixed => 'Mixed',
        };
    }
}
