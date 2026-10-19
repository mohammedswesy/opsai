<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Technician;
use App\Models\User;
use App\Support\OrganizationContext;
use Carbon\Carbon;

class GetAvailableSlotTool extends ApplicationTool
{
    public function name(): string
    {
        return 'get_available_slot';
    }

    public function description(): string
    {
        return 'Check whether a technician in this organization is free for one visit window. available is true only when a real technician has no overlapping appointment. Do not invent another time.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'starts_at' => ['type' => 'string'],
            'ends_at' => ['type' => 'string'],
        ], ['starts_at', 'ends_at']);
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
        $timezone = OrganizationContext::current()->organization->timezone;
        $start = Carbon::parse($arguments['starts_at'], $timezone)->utc();
        $end = Carbon::parse($arguments['ends_at'], $timezone)->utc();

        if ($end->lessThanOrEqualTo($start)) {
            return ['success' => false, 'data' => null, 'message' => 'The visit must end after it starts.'];
        }

        $technician = Technician::query()
            ->with('user:id,name')
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->first(function (Technician $technician) use ($start, $end) {
                return ! Appointment::query()
                    ->where('technician_id', $technician->id)
                    ->where('status', '!=', AppointmentStatus::Cancelled->value)
                    ->where('scheduled_start', '<', $end)
                    ->where('scheduled_end', '>', $start)
                    ->exists();
            });

        if ($technician === null) {
            return $this->ok([
                'available' => false,
                'starts_at' => $arguments['starts_at'],
                'ends_at' => $arguments['ends_at'],
            ], 'appointments');
        }

        return $this->ok([
            'available' => true,
            'technician_name' => $technician->user?->name,
            'starts_at' => $arguments['starts_at'],
            'ends_at' => $arguments['ends_at'],
        ], 'appointments');
    }
}
