<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canIssue(): bool
    {
        return $this === self::Draft;
    }

    public function canEdit(): bool
    {
        return $this === self::Draft;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Issued, self::Overdue], true);
    }

    public function canAcceptPayment(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid, self::Overdue], true);
    }

    public function isOutstanding(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid, self::Overdue], true);
    }
}
