<?php

namespace App\Services\Operations;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\DispatchBoardController;
use App\Models\Appointment;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Support\OrganizationContext;
use App\Support\RecordReference;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only schedule lists for the dashboard and the technician page. Callers decide who
 * may see what; every query here is already organization-scoped by the model global scope.
 * Booked minutes follow the dispatch board rule, against DispatchBoardController::WORKING_MINUTES.
 */
class ScheduleQueries
{
    /** Appointment statuses that no longer need anyone to act. */
    private const CLOSED_APPOINTMENT_STATUSES = ['completed', 'cancelled', 'no_show'];

    /**
     * Requests waiting for a visit plus work orders with no technician.
     */
    public function unassignedCount(?string $branchId, bool $countRequests, bool $countWorkOrders): int
    {
        $requests = $countRequests
            ? ServiceRequest::query()
                ->whereIn('status', [ServiceRequestStatus::New->value, ServiceRequestStatus::Qualified->value])
                ->whereDoesntHave('workOrder')
                ->whereDoesntHave('appointments', fn (Builder $query) => $query->where('status', '!=', AppointmentStatus::Cancelled->value))
                ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->count()
            : 0;

        $workOrders = $countWorkOrders
            ? WorkOrder::query()
                ->whereNull('technician_id')
                ->whereIn('status', $this->activeWorkOrderStatuses())
                ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->count()
            : 0;

        return $requests + $workOrders;
    }

    /**
     * Appointments whose end time has passed but are still open.
     *
     * @param  string|null  $onlyTechnicianId  null for everyone, '' for nobody, an id for one technician
     */
    public function overdueAppointmentCount(?string $branchId, ?string $onlyTechnicianId): int
    {
        return $this->appointmentQuery($branchId, $onlyTechnicianId)
            ->where('scheduled_end', '<', now())
            ->whereNotIn('status', self::CLOSED_APPOINTMENT_STATUSES)
            ->count();
    }

    /**
     * The next open appointments for the rest of today, in start order.
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingToday(?string $branchId, ?string $onlyTechnicianId, int $limit = 5): array
    {
        [, $dayEnd] = $this->today();

        return $this->appointmentQuery($branchId, $onlyTechnicianId)
            ->with(['customer:id,name,address', 'serviceRequest:id,subject,priority,location', 'technician.user:id,name'])
            ->where('scheduled_end', '>=', now())
            ->where('scheduled_start', '<', $dayEnd->copy()->utc())
            ->whereNotIn('status', self::CLOSED_APPOINTMENT_STATUSES)
            ->orderBy('scheduled_start')
            ->limit($limit)
            ->get()
            ->map(fn (Appointment $appointment) => $this->appointmentItem($appointment))
            ->values()
            ->all();
    }

    /**
     * One technician's appointments and work orders for today, in start order. A work
     * order booked through one of those appointments is folded into the appointment row.
     *
     * @return list<array<string, mixed>>
     */
    public function myDay(Technician $technician): array
    {
        [$dayStart, $dayEnd] = $this->today();
        $appointments = $this->dayAppointments($technician, $dayStart, $dayEnd);
        $workOrders = $this->dayWorkOrders($technician, $dayStart, $dayEnd)
            ->reject(fn (WorkOrder $order) => $order->appointment_id !== null && $appointments->contains('id', $order->appointment_id));

        return $appointments->map(fn (Appointment $appointment) => $this->appointmentItem($appointment))
            ->merge($workOrders->map(fn (WorkOrder $order) => $this->workOrderItem($order, $dayStart, $dayEnd)))
            ->sortBy(fn (array $item) => $item['start'] ?? '9999')
            ->values()
            ->all();
    }

    /**
     * @return array{upcoming_appointments: list<array<string, mixed>>, active_work_orders: list<array<string, mixed>>, utilization: array{date: string, booked_minutes: int, working_minutes: int, percent: int}}
     */
    public function technicianSchedule(Technician $technician, int $limit = 10): array
    {
        [$dayStart, $dayEnd] = $this->today();
        $booked = $this->bookedMinutes(
            $this->dayAppointments($technician, $dayStart, $dayEnd),
            $this->dayWorkOrders($technician, $dayStart, $dayEnd),
            $dayStart,
            $dayEnd,
        );

        return [
            'upcoming_appointments' => Appointment::query()
                ->with(['customer:id,name,address', 'serviceRequest:id,subject,priority,location', 'technician.user:id,name'])
                ->where('technician_id', $technician->id)
                ->where('scheduled_end', '>=', now())
                ->whereNotIn('status', self::CLOSED_APPOINTMENT_STATUSES)
                ->orderBy('scheduled_start')
                ->limit($limit)
                ->get()
                ->map(fn (Appointment $appointment) => $this->appointmentItem($appointment))
                ->values()
                ->all(),
            'active_work_orders' => WorkOrder::query()
                ->with(['customer:id,name,address', 'serviceRequest:id,location', 'appointment:id,scheduled_start,scheduled_end', 'technician.user:id,name'])
                ->where('technician_id', $technician->id)
                ->whereIn('status', $this->activeWorkOrderStatuses())
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (WorkOrder $order) => $this->workOrderItem($order))
                ->values()
                ->all(),
            'utilization' => [
                'date' => $dayStart->toDateString(),
                'booked_minutes' => $booked,
                'working_minutes' => DispatchBoardController::WORKING_MINUTES,
                'percent' => (int) round(($booked / DispatchBoardController::WORKING_MINUTES) * 100),
            ],
        ];
    }

    /**
     * @return Builder<Appointment>
     */
    private function appointmentQuery(?string $branchId, ?string $onlyTechnicianId): Builder
    {
        return Appointment::query()
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($onlyTechnicianId !== null, fn (Builder $query) => $query->where('technician_id', $onlyTechnicianId));
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function dayAppointments(Technician $technician, Carbon $dayStart, Carbon $dayEnd): Collection
    {
        return Appointment::query()
            ->with(['customer:id,name,address', 'serviceRequest:id,subject,priority,location', 'technician.user:id,name'])
            ->where('technician_id', $technician->id)
            ->where('scheduled_start', '<', $dayEnd->copy()->utc())
            ->where('scheduled_end', '>', $dayStart->copy()->utc())
            ->orderBy('scheduled_start')
            ->get();
    }

    /**
     * Same day rule as the dispatch board: the linked appointment overlaps today, or work started today.
     *
     * @return Collection<int, WorkOrder>
     */
    private function dayWorkOrders(Technician $technician, Carbon $dayStart, Carbon $dayEnd): Collection
    {
        return WorkOrder::query()
            ->with(['customer:id,name,address', 'serviceRequest:id,location', 'appointment:id,scheduled_start,scheduled_end', 'technician.user:id,name'])
            ->where('technician_id', $technician->id)
            ->where(function (Builder $query) use ($dayStart, $dayEnd) {
                $query->whereHas('appointment', function (Builder $query) use ($dayStart, $dayEnd) {
                    $query->where('scheduled_start', '<', $dayEnd->copy()->utc())
                        ->where('scheduled_end', '>', $dayStart->copy()->utc());
                })->orWhereBetween('started_at', [$dayStart->copy()->utc(), $dayEnd->copy()->utc()]);
            })
            ->get();
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @param  Collection<int, WorkOrder>  $workOrders
     */
    private function bookedMinutes(Collection $appointments, Collection $workOrders, Carbon $dayStart, Carbon $dayEnd): int
    {
        $minutes = 0;

        foreach ($appointments as $appointment) {
            if ($appointment->status !== AppointmentStatus::Cancelled) {
                $minutes += $this->overlapMinutes($appointment->scheduled_start, $appointment->scheduled_end, $dayStart, $dayEnd);
            }
        }

        foreach ($workOrders as $order) {
            if ($order->appointment_id !== null && $appointments->contains('id', $order->appointment_id)) {
                continue;
            }

            if ($order->started_at !== null && $order->completed_at !== null) {
                $minutes += $this->overlapMinutes($order->started_at, $order->completed_at, $dayStart, $dayEnd);
            }
        }

        return $minutes;
    }

    private function overlapMinutes(Carbon $start, Carbon $end, Carbon $dayStart, Carbon $dayEnd): int
    {
        $from = $start->greaterThan($dayStart) ? $start : $dayStart;
        $to = $end->lessThan($dayEnd) ? $end : $dayEnd;

        return $to->lessThanOrEqualTo($from) ? 0 : (int) $from->diffInMinutes($to, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentItem(Appointment $appointment): array
    {
        $request = $appointment->serviceRequest;

        return [
            'type' => 'appointment',
            'id' => $appointment->id,
            'reference' => RecordReference::fromId('APT', $appointment->id),
            'title' => $request?->subject ?? $appointment->customer?->name,
            'start' => $this->localTime($appointment->scheduled_start),
            'end' => $this->localTime($appointment->scheduled_end),
            'status' => $appointment->status->value,
            'priority' => $request?->priority?->value,
            'customer_name' => $appointment->customer?->name,
            'technician_name' => $appointment->technician?->user?->name,
            'location' => $request?->location ?? $appointment->customer?->address,
            'appointment_id' => $appointment->id,
            'work_order_id' => $appointment->work_order_id,
        ];
    }

    /**
     * Times come from the linked appointment. Given a day, a job whose appointment falls on
     * another day (started late, or carried over) shows when the work happened instead.
     *
     * @return array<string, mixed>
     */
    private function workOrderItem(WorkOrder $order, ?Carbon $dayStart = null, ?Carbon $dayEnd = null): array
    {
        $appointment = $order->appointment;
        $useAppointment = $appointment !== null && ($dayStart === null
            || ($appointment->scheduled_start->lessThan($dayEnd) && $appointment->scheduled_end->greaterThan($dayStart)));

        return [
            'type' => 'work_order',
            'id' => $order->id,
            'reference' => RecordReference::fromId('WO', $order->id),
            'title' => $order->title,
            'start' => $this->localTime($useAppointment ? $appointment->scheduled_start : $order->started_at),
            'end' => $this->localTime($useAppointment ? $appointment->scheduled_end : $order->completed_at),
            'status' => $order->status->value,
            'priority' => $order->priority->value,
            'customer_name' => $order->customer?->name,
            'technician_name' => $order->technician?->user?->name,
            'location' => $order->serviceRequest?->location ?? $order->customer?->address,
            'appointment_id' => $order->appointment_id,
            'work_order_id' => $order->id,
        ];
    }

    /**
     * @return list<string>
     */
    private function activeWorkOrderStatuses(): array
    {
        return array_values(array_map(
            fn (WorkOrderStatus $status) => $status->value,
            array_filter(WorkOrderStatus::cases(), fn (WorkOrderStatus $status) => $status->isActive()),
        ));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function today(): array
    {
        $timezone = OrganizationContext::current()->organization->timezone;

        return [Carbon::now($timezone)->startOfDay(), Carbon::now($timezone)->endOfDay()];
    }

    private function localTime(?Carbon $value): ?string
    {
        return $value?->timezone(OrganizationContext::current()->organization->timezone)->toIso8601String();
    }
}
