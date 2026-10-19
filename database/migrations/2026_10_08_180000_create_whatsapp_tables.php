<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->ulid('user_id')->nullable()->change();
            $table->foreignUlid('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('channel')->default('staff')->after('customer_id');
        });

        Schema::table('ai_actions', function (Blueprint $table) {
            $table->ulid('user_id')->nullable()->change();
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('type');
            $table->string('status');
            $table->text('credentials')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'type']);
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('contact_phone');
            $table->string('contact_name')->nullable();
            $table->string('status');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'contact_phone']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->foreignUlid('whatsapp_conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('external_message_id')->nullable();
            $table->string('direction');
            $table->string('from_number');
            $table->string('to_number');
            $table->string('message_type');
            $table->text('text')->nullable();
            $table->json('media')->nullable();
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_message_id']);
            $table->index(['organization_id', 'whatsapp_conversation_id']);
        });

        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('external_event_id');
            $table->string('event_type');
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->string('status');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('integrations');

        Schema::table('ai_actions', function (Blueprint $table) {
            $table->ulid('user_id')->nullable(false)->change();
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('channel');
            $table->ulid('user_id')->nullable(false)->change();
        });
    }
};
