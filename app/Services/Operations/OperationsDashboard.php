<?php

namespace App\Services\Operations;

use App\Enums\AvailabilityStatus;
use App\Enums\RecordStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ServiceRequest;
use App\Models\Task;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Support\OrganizationContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OperationsDashboard
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(Request $request): array
    {
        $branchId = $request->string('branch_id')->toString();
        if ($branchId !== '' && Branch::query()->whereKey($branchId)->doesntExist()) {
            abort(404);
        }

        $timezone = OrganizationContext::current()->organization->timezone;
        $start = Carbon::now($timezone)->startOfDay()->utc();
        $end = Carbon::now($timezone)->endOfDay()->utc();

        $requests = ServiceRequest::query()->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId));
        $appointments = Appointment::query()->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId));
        $workOrders = WorkOrder::query()->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId));
        $technicians = Technician::query()
            ->where('status', RecordStatus::Active)
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId));
        $tasks = Task::query()->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId));

        $requestsByDay = (clone $requests)
            ->where('requested_at', '>=', Carbon::now($timezone)->subDays(13)->startOfDay()->utc())
            ->get(['requested_at'])
            ->groupBy(fn (ServiceRequest $request) => $request->requested_at->timezone($timezone)->toDateString())
            ->map->count();

        $days = collect(range(13, 0))->map(function (int $daysAgo) use ($timezone, $requestsByDay) {
            $date = Carbon::now($timezone)->subDays($daysAgo)->toDateString();

            return ['date' => $date, 'count' => (int) ($requestsByDay[$date] ?? 0)];
        })->values();

        $byStatus = (clone $workOrders)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (WorkOrder $row) => [
                $row->status instanceof WorkOrderStatus ? $row->status->value : (string) $row->status => (int) $row->total,
            ]);

        $workload = (clone $workOrders)
            ->whereIn('status', array_map(fn (WorkOrderStatus $status) => $status->value, array_filter(
                WorkOrderStatus::cases(),
                fn (WorkOrderStatus $status) => $status->isActive(),
            )))
            ->whereNotNull('technician_id')
            ->with('technician.user:id,name')
            ->get()
            ->groupBy('technician_id')
            ->map(fn ($orders) => [
                'name' => $orders->first()->technician?->user?->name ?? 'Technician',
                'count' => $orders->count(),
            ])
            ->values();

        return [
            'open_requests' => (clone $requests)->whereIn('status', array_map(
                fn (ServiceRequestStatus $status) => $status->value,
                array_filter(ServiceRequestStatus::cases(), fn (ServiceRequestStatus $status) => $status->isOpen()),
            ))->count(),
            'todays_appointments' => (clone $appointments)->whereBetween('scheduled_start', [$start, $end])->count(),
            'active_work_orders' => (clone $workOrders)->whereIn('status', array_map(
                fn (WorkOrderStatus $status) => $status->value,
                array_filter(WorkOrderStatus::cases(), fn (WorkOrderStatus $status) => $status->isActive()),
            ))->count(),
            'completed_work_orders' => (clone $workOrders)->where('status', WorkOrderStatus::Completed)->count(),
            'available_technicians' => (clone $technicians)->where('availability_status', AvailabilityStatus::Available)->count(),
            'pending_tasks' => (clone $tasks)->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])->count(),
            'requests_by_day' => $days,
            'work_orders_by_status' => collect(WorkOrderStatus::cases())->map(fn (WorkOrderStatus $status) => [
                'status' => $status->value,
                'count' => (int) ($byStatus[$status->value] ?? 0),
            ])->values(),
            'technician_workload' => $workload,
        ];
    }
}
