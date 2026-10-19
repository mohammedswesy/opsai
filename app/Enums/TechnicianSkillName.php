<?php

namespace App\Enums;

enum TechnicianSkillName: string
{
    case Hvac = 'hvac';
    case Electrical = 'electrical';
    case Plumbing = 'plumbing';
    case Cleaning = 'cleaning';
    case PestControl = 'pest_control';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
