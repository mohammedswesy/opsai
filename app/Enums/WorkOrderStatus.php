<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case OnTheWay = 'on_the_way';
    case Arrived = 'arrived';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Assigned => [self::Accepted, self::Cancelled],
            self::Accepted => [self::OnTheWay, self::Cancelled],
            self::OnTheWay => [self::Arrived, self::Cancelled],
            self::Arrived => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
