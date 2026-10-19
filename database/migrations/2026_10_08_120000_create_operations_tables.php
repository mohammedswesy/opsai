<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('whatsapp_number')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'branch_id']);
            $table->index(['organization_id', 'phone']);
            $table->index(['organization_id', 'name']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->decimal('base_price', 12, 2)->nullable();
            $table->unsignedInteger('estimated_duration')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'category']);
        });

        Schema::create('technicians', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('availability_status');
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'user_id']);
            $table->unique(['organization_id', 'employee_code']);
            $table->index(['organization_id', 'availability_status']);
            $table->index(['organization_id', 'branch_id']);
        });

        Schema::create('technician_skills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('technician_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('skill');
            $table->timestamps();

            $table->unique(['technician_id', 'skill']);
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('subject');
            $table->text('description');
            $table->string('priority');
            $table->string('status');
            $table->timestamp('requested_at');
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time', 8)->nullable();
            $table->string('location')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'priority']);
            $table->index(['organization_id', 'requested_at']);
            $table->index(['organization_id', 'customer_id']);
            $table->index(['organization_id', 'branch_id']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('technician_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scheduled_start']);
            $table->index(['technician_id', 'scheduled_start', 'scheduled_end']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority');
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('technician_notes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'technician_id']);
            $table->index(['organization_id', 'customer_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignUlid('work_order_id')->nullable()->after('service_request_id')->constrained()->nullOnDelete();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority');
            $table->string('status');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('related_type')->nullable();
            $table->ulid('related_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'assigned_to']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type');
            $table->ulid('subject_id');
            $table->string('action');
            $table->string('description')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('tasks');
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('technician_skills');
        Schema::dropIfExists('technicians');
        Schema::dropIfExists('services');
        Schema::dropIfExists('customers');
    }
};
