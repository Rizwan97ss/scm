<?php

namespace App\Enums;

enum CourseMaterialType: string
{
    case Document = 'document';
    case Link = 'link';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Document => 'Document',
            self::Link => 'Link',
            self::Video => 'Video',
        };
    }
}
