<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'year']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number');
            $table->string('status');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'invoice_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'customer_id']);
            $table->index(['organization_id', 'branch_id']);
            $table->index(['organization_id', 'due_date']);
            $table->index(['organization_id', 'issue_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 8, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('method');
            $table->string('status');
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'invoice_id']);
            $table->index(['organization_id', 'paid_at']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};
