<?php

namespace App\Enums;

enum HostelAllocationStatus: string
{
    case Allocated = 'allocated';
    case Vacated = 'vacated';

    public function label(): string
    {
        return match ($this) {
            self::Allocated => 'Allocated',
            self::Vacated => 'Vacated',
        };
    }
}
