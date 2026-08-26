<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case TransferredOut = 'transferred_out';
    case Withdrawn = 'withdrawn';
    case Graduated = 'graduated';
    case Alumni = 'alumni';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::TransferredOut => 'Transferred Out',
            self::Withdrawn => 'Withdrawn',
            self::Graduated => 'Graduated',
            self::Alumni => 'Alumni',
        };
    }

    /**
     * Statuses that count as currently enrolled / on the active roster.
     */
    public static function activeStatuses(): array
    {
        return [self::Active];
    }
}
