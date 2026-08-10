<?php

namespace App\Enums;

enum RemarkCategory: string
{
    case Academic = 'academic';
    case Behavioral = 'behavioral';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Academic',
            self::Behavioral => 'Behavioral',
            self::General => 'General',
        };
    }
}
