<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Operations\AppointmentService;
use App\Support\OrganizationContext;
use Carbon\Carbon;

class CreateAppointmentTool extends ApplicationTool
{
    public function name(): string
    {
        return 'create_appointment';
    }

    public function description(): string
    {
        return 'Propose an appointment. It is saved only after the user confirms.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'customer_name' => ['type' => 'string'],
            'customer_id' => ['type' => 'string'],
            'technician_name' => ['type' => 'string'],
            'starts_at' => ['type' => 'string'],
            'ends_at' => ['type' => 'string'],
            'service_request_id' => ['type' => 'string'],
        ], ['technician_name', 'starts_at', 'ends_at']);
    }

    public function authorize(User $user): bool
    {
        return $user->can('create', Appointment::class);
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function preview(array $arguments): array
    {
        return [
            ['key' => 'customer', 'value' => (string) ($arguments['customer_name'] ?? '')],
            ['key' => 'technician', 'value' => (string) ($arguments['technician_name'] ?? '')],
            ['key' => 'start', 'value' => (string) ($arguments['starts_at'] ?? '')],
            ['key' => 'end', 'value' => (string) ($arguments['ends_at'] ?? '')],
        ];
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $resolver = app(RecordResolver::class);
        $customer = $resolver->customer($arguments['customer_id'] ?? null, $arguments['customer_name'] ?? null);
        $technician = $resolver->technicianByName($arguments['technician_name']);
        $timezone = OrganizationContext::current()->organization->timezone;
        $start = Carbon::parse($arguments['starts_at'], $timezone)->utc();
        $end = Carbon::parse($arguments['ends_at'], $timezone)->utc();

        $appointment = app(AppointmentService::class)->create([
            'branch_id' => $context->scopedBranchId() ?? $customer->branch_id,
            'customer_id' => $customer->id,
            'technician_id' => $technician->id,
            'service_request_id' => $arguments['service_request_id'] ?? null,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => AppointmentStatus::Scheduled->value,
        ]);

        return $this->ok([
            'id' => $appointment->id,
            'customer' => $customer->name,
            'technician' => $technician->user?->name,
            'starts_at' => $appointment->scheduled_start?->toIso8601String(),
        ], 'appointments');
    }
}
