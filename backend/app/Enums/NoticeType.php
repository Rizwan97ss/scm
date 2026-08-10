<?php

namespace App\Enums;

enum NoticeType: string
{
    case General = 'general';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Notice',
            self::Event => 'Event',
        };
    }
}
