<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Tools\Concerns\ScopesBranch;
use App\Models\Appointment;
use App\Models\Technician;
use App\Models\User;
use App\Services\Operations\AppointmentService;
use App\Support\OrganizationContext;

class GetTodayAppointmentsTool extends ApplicationTool
{
    use ScopesBranch;

    public function name(): string
    {
        return 'get_today_appointments';
    }

    public function description(): string
    {
        return 'Appointments scheduled today in the organization timezone.';
    }

    public function schema(): array
    {
        return $this->objectSchema([]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Appointment::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $today = now(OrganizationContext::current()->organization->timezone)->toDateString();
        $page = app(AppointmentService::class)->paginate(
            $this->metricsRequest($context, ['date' => $today]),
            $this->technicianLimit($context),
        );

        return $this->ok([
            'count' => $page->total(),
            'date' => $today,
            'appointments' => collect($page->items())->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'customer' => $appointment->customer?->name,
                'technician' => $appointment->technician?->user?->name,
                'status' => $appointment->status->value,
                'starts_at' => $appointment->scheduled_start?->toIso8601String(),
            ])->all(),
        ], 'appointments');
    }

    private function technicianLimit(AIToolContext $context): ?string
    {
        if ($context->user->can('create', Appointment::class)) {
            return null;
        }

        return Technician::query()->where('user_id', $context->user->id)->value('id') ?? '';
    }
}
