<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Policies\DispatchBoardPolicy;
use App\Support\OrganizationContext;
use App\Support\RecordReference;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DispatchBoardController extends Controller
{
    /**
     * No organization working-hours setting exists.
     * Utilization and free slots use 08:00–16:00. The grid draws one hour outside that range.
     */
    public const WORK_START = '08:00';

    public const WORK_END = '16:00';

    public const WORKING_MINUTES = 480;

    public const GRID_PADDING_MINUTES = 60;

    public function __invoke(Request $request): Response
    {
        $policy = app(DispatchBoardPolicy::class);
        abort_unless($policy->viewAny($request->user()), 403);

        $timezone = OrganizationContext::current()->organization->timezone;
        $date = $this->date($request, $timezone);
        $branchId = $this->branchId($request);
        $dayStart = Carbon::parse($date, $timezone)->startOfDay();
        $dayEnd = Carbon::parse($date, $timezone)->endOfDay();
        $now = Carbon::now($timezone);
        $seeAllAppointments = $request->user()->can('create', Appointment::class);
        $seeAllWorkOrders = $request->user()->can('create', WorkOrder::class);
        $appointmentOwner = $seeAllAppointments ? null : $this->ownTechnicianId($request);
        $workOrderOwner = $seeAllWorkOrders ? null : $this->ownTechnicianId($request);

        $appointments = $policy->viewAppointments($request->user())
            ? $this->appointments($dayStart, $dayEnd, $branchId, $appointmentOwner)
            : collect();
        $workOrders = $policy->viewWorkOrders($request->user())
            ? $this->workOrders($dayStart, $dayEnd, $branchId, $workOrderOwner)
            : collect();
        $unassigned = ($seeAllAppointments || $seeAllWorkOrders)
            ? $this->unassigned($date, $dayStart, $dayEnd, $branchId, $policy, $request)
            : [];

        $technicians = $this->technicians($appointments, $workOrders, $branchId, $appointmentOwner, $workOrderOwner, $dayStart, $dayEnd);
        $counts = $this->counts($appointments, $workOrders, $unassigned, $now);

        return Inertia::render('Dispatch/Index', [
            'board' => [
                'date' => $date,
                'timezone' => $timezone,
                'now' => $now->toIso8601String(),
                'is_today' => $date === $now->toDateString(),
                'working_minutes' => self::WORKING_MINUTES,
                'hours' => $this->hours(),
                'nearest_day' => ($appointments->isEmpty() && $workOrders->isEmpty() && $unassigned === [])
                    ? $this->nearestDay($dayStart, $branchId, $appointmentOwner, $timezone, $policy, $request)
                    : null,
                'filters' => [
                    'date' => $date,
                    'branch_id' => $branchId,
                ],
                'branches' => Branch::query()->orderBy('name')->get(['id', 'name'])->map(fn (Branch $branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ])->values(),
                'can_create_appointment' => $request->user()->can('create', Appointment::class),
                'can_create_work_order' => $request->user()->can('create', WorkOrder::class),
                'counts' => $counts,
                'technicians' => $technicians,
                'unassigned' => $unassigned,
            ],
        ]);
    }

    private function date(Request $request, string $timezone): string
    {
        $date = $request->string('date')->toString();

        if ($date === '') {
            return Carbon::now($timezone)->toDateString();
        }

        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 422);

        return $date;
    }

    private function branchId(Request $request): ?string
    {
        $branchId = $request->string('branch_id')->toString();

        if ($branchId === '') {
            return null;
        }

        abort_unless(Branch::query()->whereKey($branchId)->exists(), 404);

        return $branchId;
    }

    private function ownTechnicianId(Request $request): string
    {
        return Technician::query()->where('user_id', $request->user()->id)->value('id') ?? '';
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function appointments(Carbon $dayStart, Carbon $dayEnd, ?string $branchId, ?string $onlyTechnicianId)
    {
        return Appointment::query()
            ->with(['customer:id,name', 'serviceRequest.service:id,category,name', 'technician.user:id,name'])
            ->where('scheduled_start', '<', $dayEnd->copy()->utc())
            ->where('scheduled_end', '>', $dayStart->copy()->utc())
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($onlyTechnicianId !== null, fn ($query) => $query->where('technician_id', $onlyTechnicianId))
            ->orderBy('scheduled_start')
            ->get();
    }

    /**
     * @return Collection<int, WorkOrder>
     */
    private function workOrders(Carbon $dayStart, Carbon $dayEnd, ?string $branchId, ?string $onlyTechnicianId)
    {
        return WorkOrder::query()
            ->with(['customer:id,name', 'serviceRequest.service:id,category,name', 'appointment', 'technician.user:id,name'])
            ->whereNotNull('technician_id')
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($onlyTechnicianId !== null, fn ($query) => $query->where('technician_id', $onlyTechnicianId))
            ->where(function ($query) use ($dayStart, $dayEnd) {
                $query->whereHas('appointment', function ($query) use ($dayStart, $dayEnd) {
                    $query->where('scheduled_start', '<', $dayEnd->copy()->utc())
                        ->where('scheduled_end', '>', $dayStart->copy()->utc());
                })->orWhereBetween('started_at', [$dayStart->copy()->utc(), $dayEnd->copy()->utc()]);
            })
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unassigned(string $date, Carbon $dayStart, Carbon $dayEnd, ?string $branchId, DispatchBoardPolicy $policy, Request $request): array
    {
        $items = [];

        if ($policy->viewRequests($request->user())) {
            $requests = ServiceRequest::query()
                ->with(['customer:id,name', 'service:id,category,name,estimated_duration'])
                ->whereIn('status', [ServiceRequestStatus::New->value, ServiceRequestStatus::Qualified->value])
                ->whereDoesntHave('workOrder')
                ->whereDoesntHave('appointments', function ($query) use ($dayStart, $dayEnd) {
                    $query->where('status', '!=', AppointmentStatus::Cancelled->value)
                        ->where('scheduled_start', '<', $dayEnd->copy()->utc())
                        ->where('scheduled_end', '>', $dayStart->copy()->utc());
                })
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->where(function ($query) use ($date, $dayStart, $dayEnd) {
                    $query->whereDate('preferred_date', $date)
                        ->orWhere(function ($query) use ($dayStart, $dayEnd) {
                            $query->whereNull('preferred_date')->whereBetween('requested_at', [$dayStart->copy()->utc(), $dayEnd->copy()->utc()]);
                        });
                })
                ->orderBy('preferred_time')
                ->get();

            foreach ($requests as $item) {
                $items[] = $this->requestItem($item, $date);
            }
        }

        if ($policy->viewWorkOrders($request->user())) {
            $orders = WorkOrder::query()
                ->with(['customer:id,name', 'serviceRequest.service:id,category,name,estimated_duration'])
                ->whereNull('technician_id')
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->whereHas('serviceRequest', function ($query) use ($date, $dayStart, $dayEnd) {
                    $query->whereDate('preferred_date', $date)
                        ->orWhere(function ($query) use ($dayStart, $dayEnd) {
                            $query->whereNull('preferred_date')->whereBetween('requested_at', [$dayStart->copy()->utc(), $dayEnd->copy()->utc()]);
                        });
                })
                ->get();

            foreach ($orders as $order) {
                $items[] = $this->workOrderItem($order, $date);
            }
        }

        return $items;
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @param  Collection<int, WorkOrder>  $workOrders
     * @return list<array<string, mixed>>
     */
    private function technicians($appointments, $workOrders, ?string $branchId, ?string $appointmentOwner, ?string $workOrderOwner, Carbon $dayStart, Carbon $dayEnd): array
    {
        $ids = $appointments->pluck('technician_id')
            ->merge($workOrders->pluck('technician_id'))
            ->filter()
            ->unique()
            ->values();

        if ($appointmentOwner === null && $workOrderOwner === null) {
            $roster = Technician::query()
                ->where('status', 'active')
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->pluck('id');
            $ids = $ids->merge($roster)->unique()->values();
        } elseif ($appointmentOwner !== null && $appointmentOwner !== '') {
            $ids = collect([$appointmentOwner]);
        } elseif ($workOrderOwner !== null && $workOrderOwner !== '') {
            $ids = collect([$workOrderOwner]);
        } else {
            $ids = collect();
        }

        $technicians = Technician::query()
            ->with(['user:id,name', 'branch:id,name', 'skills'])
            ->whereIn('id', $ids)
            ->get();

        $roles = OrganizationContext::current()->organization->members()
            ->whereIn('users.id', $technicians->pluck('user_id'))
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->pivot->role]);

        return $technicians->map(function (Technician $technician) use ($appointments, $workOrders, $roles, $dayStart, $dayEnd) {
            $ownAppointments = $appointments->where('technician_id', $technician->id)->values();
            $ownOrders = $workOrders->where('technician_id', $technician->id)->values();
            $booked = $this->bookedMinutes($ownAppointments, $ownOrders, $dayStart, $dayEnd);

            return [
                'id' => $technician->id,
                'name' => $technician->user?->name,
                'role' => $roles[$technician->user_id] ?? null,
                'skills' => $technician->skills->map(fn ($skill) => $skill->skill->value)->values(),
                'availability' => $technician->availability_status->value,
                'branch' => $technician->branch?->name,
                'booked_minutes' => $booked,
                'utilization' => (int) round(($booked / self::WORKING_MINUTES) * 100),
                'appointments' => $ownAppointments->map(fn (Appointment $appointment) => $this->appointmentPayload($appointment))->values(),
                'work_orders' => $ownOrders->map(fn (WorkOrder $order) => $this->assignedWorkOrderPayload($order))->values(),
            ];
        })->sortBy('name')->values()->all();
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @param  Collection<int, WorkOrder>  $workOrders
     */
    private function bookedMinutes($appointments, $workOrders, Carbon $dayStart, Carbon $dayEnd): int
    {
        $minutes = 0;

        foreach ($appointments as $appointment) {
            if ($appointment->status === AppointmentStatus::Cancelled) {
                continue;
            }

            $minutes += $this->overlapMinutes($appointment->scheduled_start, $appointment->scheduled_end, $dayStart, $dayEnd);
        }

        $appointmentIds = $appointments->pluck('id');

        foreach ($workOrders as $order) {
            if ($order->appointment_id !== null && $appointmentIds->contains($order->appointment_id)) {
                continue;
            }

            if ($order->started_at === null || $order->completed_at === null) {
                continue;
            }

            $minutes += $this->overlapMinutes($order->started_at, $order->completed_at, $dayStart, $dayEnd);
        }

        return $minutes;
    }

    private function overlapMinutes(Carbon $start, Carbon $end, Carbon $dayStart, Carbon $dayEnd): int
    {
        $from = $start->greaterThan($dayStart) ? $start : $dayStart;
        $to = $end->lessThan($dayEnd) ? $end : $dayEnd;

        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }

        return (int) $from->diffInMinutes($to, true);
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @param  Collection<int, WorkOrder>  $workOrders
     * @param  list<array<string, mixed>>  $unassigned
     * @return array{total: int, completed: int, unassigned: int, overdue: int}
     */
    private function counts($appointments, $workOrders, array $unassigned, Carbon $now): array
    {
        $linked = $appointments->pluck('id');
        $extraOrders = $workOrders->filter(fn (WorkOrder $order) => $order->appointment_id === null || ! $linked->contains($order->appointment_id));
        $completed = $appointments->where('status', AppointmentStatus::Completed)->count()
            + $extraOrders->where('status', WorkOrderStatus::Completed)->count();
        $overdue = $appointments->filter(function (Appointment $appointment) use ($now) {
            return $appointment->scheduled_end->lessThan($now)
                && ! in_array($appointment->status, [AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true);
        })->count();

        foreach ($unassigned as $item) {
            if (($item['requested_at'] ?? null) !== null && Carbon::parse($item['requested_at'])->lessThan($now)) {
                $overdue++;
            }
        }

        return [
            'total' => $appointments->count() + $extraOrders->count() + count($unassigned),
            'completed' => $completed,
            'unassigned' => count($unassigned),
            'overdue' => $overdue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentPayload(Appointment $appointment): array
    {
        $request = $appointment->serviceRequest;

        return [
            'id' => $appointment->id,
            'reference' => RecordReference::fromId('APT', $appointment->id),
            'title' => $request?->subject ?? $appointment->customer?->name,
            'start' => $this->localTime($appointment->scheduled_start),
            'end' => $this->localTime($appointment->scheduled_end),
            'status' => $appointment->status->value,
            'priority' => $request?->priority?->value,
            'category' => $request?->service?->category,
            'customer_name' => $appointment->customer?->name,
            'technician_id' => $appointment->technician_id,
            'service_request_id' => $appointment->service_request_id,
            'work_order_id' => $appointment->work_order_id,
            'customer_id' => $appointment->customer_id,
            'branch_id' => $appointment->branch_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignedWorkOrderPayload(WorkOrder $order): array
    {
        $start = $order->appointment?->scheduled_start ?? $order->started_at;
        $end = $order->appointment?->scheduled_end ?? $order->completed_at;

        return [
            'id' => $order->id,
            'reference' => RecordReference::fromId('WO', $order->id),
            'title' => $order->title,
            'start' => $this->localTime($start),
            'end' => $this->localTime($end),
            'status' => $order->status->value,
            'priority' => $order->priority->value,
            'category' => $order->serviceRequest?->service?->category,
            'customer_name' => $order->customer?->name,
            'technician_id' => $order->technician_id,
            'service_request_id' => $order->service_request_id,
            'appointment_id' => $order->appointment_id,
            'customer_id' => $order->customer_id,
            'branch_id' => $order->branch_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestItem(ServiceRequest $item, string $date): array
    {
        return [
            'id' => $item->id,
            'type' => 'request',
            'reference' => RecordReference::fromId('REQ', $item->id),
            'title' => $item->subject,
            'priority' => $item->priority->value,
            'category' => $item->service?->category,
            'requested_at' => $this->requestedAt($date, $item->preferred_time, $item->requested_at),
            'area' => $item->location,
            'customer_name' => $item->customer?->name,
            'customer_id' => $item->customer_id,
            'service_request_id' => $item->id,
            'branch_id' => $item->branch_id,
            'estimated_minutes' => $item->service?->estimated_duration,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workOrderItem(WorkOrder $order, string $date): array
    {
        $request = $order->serviceRequest;

        return [
            'id' => $order->id,
            'type' => 'work_order',
            'reference' => RecordReference::fromId('WO', $order->id),
            'title' => $order->title,
            'priority' => $order->priority->value,
            'category' => $request?->service?->category,
            'requested_at' => $this->requestedAt($date, $request?->preferred_time, $request?->requested_at),
            'area' => $request?->location,
            'customer_name' => $order->customer?->name,
            'customer_id' => $order->customer_id,
            'service_request_id' => $order->service_request_id,
            'branch_id' => $order->branch_id,
            'estimated_minutes' => $request?->service?->estimated_duration,
        ];
    }

    /**
     * @return array{work_start: string, work_end: string, working_minutes: int, grid_start: string, grid_end: string, grid_padding_minutes: int}
     */
    private function hours(): array
    {
        return [
            'work_start' => self::WORK_START,
            'work_end' => self::WORK_END,
            'working_minutes' => self::WORKING_MINUTES,
            'grid_start' => Carbon::parse(self::WORK_START)->subMinutes(self::GRID_PADDING_MINUTES)->format('H:i'),
            'grid_end' => Carbon::parse(self::WORK_END)->addMinutes(self::GRID_PADDING_MINUTES)->format('H:i'),
            'grid_padding_minutes' => self::GRID_PADDING_MINUTES,
        ];
    }

    private function nearestDay(Carbon $dayStart, ?string $branchId, ?string $onlyTechnicianId, string $timezone, DispatchBoardPolicy $policy, Request $request): ?string
    {
        if (! $policy->viewAppointments($request->user())) {
            return null;
        }

        $base = Appointment::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($onlyTechnicianId !== null, fn ($query) => $query->where('technician_id', $onlyTechnicianId));
        $before = (clone $base)->where('scheduled_start', '<', $dayStart)->orderByDesc('scheduled_start')->value('scheduled_start');
        $after = (clone $base)->where('scheduled_start', '>=', $dayStart->copy()->addDay())->orderBy('scheduled_start')->value('scheduled_start');
        $selected = $dayStart->copy()->startOfDay();
        $candidates = collect([$before, $after])
            ->filter()
            ->map(fn ($value) => Carbon::parse($value)->timezone($timezone)->startOfDay())
            ->sort(function (Carbon $left, Carbon $right) use ($selected) {
                $distance = (int) $selected->diffInDays($left, true) <=> (int) $selected->diffInDays($right, true);

                return $distance !== 0 ? $distance : $right->getTimestamp() <=> $left->getTimestamp();
            })
            ->values();

        return $candidates->first()?->toDateString();
    }

    private function localTime(?Carbon $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->timezone(OrganizationContext::current()->organization->timezone)->toIso8601String();
    }

    private function requestedAt(string $date, ?string $time, ?Carbon $fallback): ?string
    {
        if ($time !== null && $time !== '') {
            return Carbon::parse($date.' '.$time, OrganizationContext::current()->organization->timezone)->toIso8601String();
        }

        return $fallback?->toIso8601String();
    }
}
