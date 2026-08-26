<?php

namespace App\Enums;

/**
 * Shared by Notice (Notice Board) and Announcement (Communication) —
 * who a posted notice or a sent broadcast is meant for.
 */
enum Audience: string
{
    case All = 'all';
    case Students = 'students';
    case Staff = 'staff';
    case Parents = 'parents';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Everyone',
            self::Students => 'Students',
            self::Staff => 'Staff',
            self::Parents => 'Parents',
        };
    }
}
