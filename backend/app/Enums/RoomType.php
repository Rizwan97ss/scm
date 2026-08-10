<?php

namespace App\Enums;

enum RoomType: string
{
    case Classroom = 'classroom';
    case Lab = 'lab';
    case Hall = 'hall';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Classroom => 'Classroom',
            self::Lab => 'Lab',
            self::Hall => 'Hall',
            self::Other => 'Other',
        };
    }
}
