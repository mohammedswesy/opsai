<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToOrganization, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'customer_id',
        'work_order_id',
        'invoice_number',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'subtotal',
        'discount',
        'tax',
        'total',
        'amount_paid',
        'amount_due',
        'notes',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Overdue->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('amount_due', '>', 0)
            ->where(function (Builder $query): void {
                $query->where('status', InvoiceStatus::Overdue->value)
                    ->orWhere(function (Builder $query): void {
                        $query->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
                            ->whereDate('due_date', '<', now()->toDateString());
                    });
            });
    }
}
