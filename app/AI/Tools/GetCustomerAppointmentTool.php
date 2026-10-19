<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;

class GetCustomerAppointmentTool extends ApplicationTool
{
    public function name(): string
    {
        return 'get_customer_appointment';
    }

    public function description(): string
    {
        return 'List only this identified customer’s upcoming appointments. An empty list means there is no upcoming appointment.';
    }

    public function schema(): array
    {
        return $this->objectSchema([]);
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

    public function execute(array $arguments, AIToolContext $context): array
    {
        $appointments = Appointment::query()
            ->where('customer_id', $context->customer?->id)
            ->where('scheduled_start', '>=', now()->subHour())
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->orderBy('scheduled_start')
            ->limit(5)
            ->get();

        return $this->ok([
            'count' => $appointments->count(),
            'appointments' => $appointments->map(fn (Appointment $appointment) => [
                'starts_at' => $appointment->scheduled_start?->toIso8601String(),
                'status' => $appointment->status->value,
            ])->all(),
        ], 'appointments');
    }
}
