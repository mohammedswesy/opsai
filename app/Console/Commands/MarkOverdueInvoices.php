<?php

namespace App\Console\Commands;

use App\Services\Finance\InvoiceLifecycleService;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark issued and partially paid invoices overdue when their due date has passed';

    public function handle(InvoiceLifecycleService $invoices): int
    {
        $count = $invoices->markOverdue();
        $this->info("Marked {$count} invoices overdue.");

        return self::SUCCESS;
    }
}
