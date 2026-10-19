<?php

namespace App\Services\Operations;

use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Appointment;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\WorkOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function __construct(
        private readonly ActivityLogger $activities,
        private readonly ServiceRequestService $requests,
        private readonly AppointmentService $appointments,
    ) {}

    public function paginate(Request $request, ?string $onlyTechnicianId = null): LengthAwarePaginator
    {
        return WorkOrder::query()
            ->with(['customer:id,name', 'technician.user:id,name', 'serviceRequest:id,subject'])
            ->when($onlyTechnicianId !== null, fn ($query) => $query->where('technician_id', $onlyTechnicianId))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->string('branch_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->toString();
                $query->where('title', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WorkOrder
    {
        return DB::transaction(function () use ($data) {
            $request = ServiceRequest::query()->findOrFail($data['service_request_id']);

            if ($request->customer_id !== $data['customer_id']) {
                throw ValidationException::withMessages([
                    'customer_id' => __('The customer must match the service request.'),
                ]);
            }

            $workOrder = WorkOrder::query()->create([
                ...$data,
                'status' => WorkOrderStatus::Assigned,
                'branch_id' => $data['branch_id'] ?? $request->branch_id,
            ]);

            if (! empty($data['appointment_id'])) {
                Appointment::query()->whereKey($data['appointment_id'])->update([
                    'work_order_id' => $workOrder->id,
                ]);
            }

            $workOrder->load('technician.user');
            $technicianName = $workOrder->technician?->user?->name;

            $this->activities->log($workOrder, 'work_order.created', $workOrder->title, [
                'technician_id' => $workOrder->technician_id,
                'technician_name' => $technicianName,
            ]);
            $this->activities->log($request, 'work_order.created', $workOrder->title, [
                'work_order_id' => $workOrder->id,
                'technician_id' => $workOrder->technician_id,
                'technician_name' => $technicianName,
            ]);

            if ($workOrder->technician_id) {
                $this->activities->log($request, 'technician.assigned', $technicianName, [
                    'technician_id' => $workOrder->technician_id,
                    'work_order_id' => $workOrder->id,
                ]);
            }

            return $workOrder;
        });
    }

    public function transition(WorkOrder $workOrder, WorkOrderStatus $status, ?string $notes = null): WorkOrder
    {
        if (! in_array($status, $workOrder->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('This work order cannot move from :from to :to.', [
                    'from' => $workOrder->status->value,
                    'to' => $status->value,
                ]),
            ]);
        }

        return DB::transaction(function () use ($workOrder, $status, $notes) {
            $from = $workOrder->status->value;
            $workOrder->status = $status;

            if ($status === WorkOrderStatus::InProgress && $workOrder->started_at === null) {
                $workOrder->started_at = now();
            }

            if ($status === WorkOrderStatus::Completed) {
                $workOrder->completed_at = now();
                if ($notes) {
                    $workOrder->completion_notes = $notes;
                }
                $this->requests->mark($workOrder->serviceRequest, ServiceRequestStatus::Completed);
            } elseif ($status === WorkOrderStatus::InProgress) {
                $this->requests->mark($workOrder->serviceRequest, ServiceRequestStatus::InProgress);
            }

            if ($notes && $status !== WorkOrderStatus::Completed) {
                $workOrder->technician_notes = $notes;
            }

            $workOrder->save();
            $this->activities->log($workOrder, 'work_order.status_changed', $status->value, [
                'from' => $from,
                'to' => $status->value,
            ]);

            return $workOrder;
        });
    }

    public function assign(WorkOrder $workOrder, string $technicianId): WorkOrder
    {
        $technician = Technician::query()->whereKey($technicianId)->first();

        if ($technician === null) {
            throw ValidationException::withMessages([
                'technician_id' => __('Choose a technician in this organization.'),
            ]);
        }

        $workOrder->loadMissing('appointment');
        $schedule = $workOrder->appointment;

        if ($schedule?->scheduled_start !== null && $schedule->scheduled_end !== null) {
            $this->appointments->guardSchedule(
                $technician->id,
                $schedule->scheduled_start,
                $schedule->scheduled_end,
                $schedule->status->value,
                $schedule->id,
            );
        }

        $workOrder->technician_id = $technician->id;
        $workOrder->save();
        $workOrder->load('technician.user');
        $this->activities->log($workOrder, 'technician.assigned', $workOrder->technician?->user?->name, [
            'technician_id' => $technician->id,
        ]);

        return $workOrder;
    }
}
