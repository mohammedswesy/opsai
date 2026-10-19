<?php

namespace App\Enums;

enum RequestSource: string
{
    case Manual = 'manual';
    case Whatsapp = 'whatsapp';
    case Website = 'website';
    case Phone = 'phone';
    case Email = 'email';
    case Ai = 'ai';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
