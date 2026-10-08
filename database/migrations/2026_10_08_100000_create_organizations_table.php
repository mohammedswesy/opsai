<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('legal_name')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('country', 2);
            $table->string('timezone');
            $table->string('currency', 3);
            $table->string('locale', 8);
            $table->string('logo')->nullable();
            $table->string('status');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('country');
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->primary(['organization_id', 'user_id']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
