<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('status');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role');
            $table->text('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->json('tool_results')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('ai_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('tool_name');
            $table->string('action_type');
            $table->json('arguments');
            $table->string('status');
            $table->json('result')->nullable();
            $table->string('idempotency_key');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_actions');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
