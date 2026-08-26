<?php

namespace App\Enums;

enum SettingType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Float = 'float';
    case Json = 'json';
    case Array = 'array';

    /**
     * Cast a raw stored string value to its native PHP type.
     */
    public function cast(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::String => $value,
            self::Integer => (int) $value,
            self::Float => (float) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Json, self::Array => json_decode($value, true),
        };
    }

    /**
     * Serialize a native PHP value for storage as a string.
     */
    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Json, self::Array => json_encode($value),
            self::Boolean => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
