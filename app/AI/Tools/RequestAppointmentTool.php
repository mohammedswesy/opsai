<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Enums\AppointmentStatus;
use App\Models\User;
use App\Services\Operations\AppointmentService;
use App\Support\OrganizationContext;
use Carbon\Carbon;

class RequestAppointmentTool extends ApplicationTool
{
    public function name(): string
    {
        return 'request_appointment';
    }

    public function description(): string
    {
        return 'Propose a visit for the identified customer. It is saved only after confirmation.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'technician_name' => ['type' => 'string'],
            'starts_at' => ['type' => 'string'],
            'ends_at' => ['type' => 'string'],
        ], ['technician_name', 'starts_at', 'ends_at']);
    }

    public function forStaff(): bool
    {
        return false;
    }

    public function forCustomer(): bool
    {
        return true;
    }

    public function authorize(User $user): bool
    {
        return false;
    }

    public function authorizeCustomer(AIToolContext $context): bool
    {
        return $context->customer !== null;
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function preview(array $arguments): array
    {
        return [
            ['key' => 'start', 'value' => (string) ($arguments['starts_at'] ?? '')],
            ['key' => 'end', 'value' => (string) ($arguments['ends_at'] ?? '')],
        ];
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $customer = $context->customer;

        if ($customer === null) {
            return ['success' => false, 'data' => null, 'message' => 'The customer could not be identified.'];
        }

        $technician = app(RecordResolver::class)->technicianByName($arguments['technician_name']);
        $timezone = OrganizationContext::current()->organization->timezone;
        $start = Carbon::parse($arguments['starts_at'], $timezone)->utc();
        $end = Carbon::parse($arguments['ends_at'], $timezone)->utc();

        $appointment = app(AppointmentService::class)->create([
            'branch_id' => $context->scopedBranchId() ?? $customer->branch_id,
            'customer_id' => $customer->id,
            'technician_id' => $technician->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => AppointmentStatus::Scheduled->value,
        ]);

        return $this->ok([
            'id' => $appointment->id,
            'starts_at' => $appointment->scheduled_start?->toIso8601String(),
            'subject' => 'Appointment',
        ], 'appointments');
    }
}
