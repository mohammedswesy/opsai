<?php

namespace App\Enums;

enum AiActionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Executing = 'executing';
    case Executed = 'executed';
    case Failed = 'failed';
}
