<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-organization registry of WhatsApp message templates approved in Meta. The
 * parameter mapping is the ordered list of fields filled into {{1}}, {{2}}, ...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 512);
            $table->string('language', 15);
            $table->string('purpose', 64);
            $table->string('status', 16)->default('unknown');
            $table->string('provider_status', 32)->nullable();
            $table->string('category', 32)->nullable();
            $table->json('parameters');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'language']);
            $table->index(['organization_id', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
