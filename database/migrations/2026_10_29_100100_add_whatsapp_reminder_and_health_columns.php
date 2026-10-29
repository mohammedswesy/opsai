<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Additive only: customer opt-in for business-initiated WhatsApp messages, the
 * connection health flag and reminder switch on the integration, and an idempotency
 * key so a template is sent at most once per (appointment, purpose).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp_number');
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->nullable()->after('status');
            $table->string('attention_reason', 64)->nullable()->after('reminders_enabled');
            $table->timestamp('attention_at')->nullable()->after('attention_reason');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->after('external_message_id');
            $table->unique(['organization_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn(['reminders_enabled', 'attention_reason', 'attention_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('whatsapp_opt_in_at');
        });
    }
};
