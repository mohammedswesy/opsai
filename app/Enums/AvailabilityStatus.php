<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Available = 'available';
    case Busy = 'busy';
    case Unavailable = 'unavailable';
    case OffDuty = 'off_duty';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
