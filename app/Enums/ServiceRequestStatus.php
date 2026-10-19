<?php

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Qualified, self::Scheduled, self::InProgress], true);
    }
}
