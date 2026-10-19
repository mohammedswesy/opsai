<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'year',
        'last_number',
    ];
}
