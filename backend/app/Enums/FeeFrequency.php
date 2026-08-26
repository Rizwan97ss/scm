<?php

namespace App\Enums;

enum FeeFrequency: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Term = 'term';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-time',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Term => 'Term',
            self::Annual => 'Annual',
        };
    }
}
