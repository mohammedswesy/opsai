<?php

namespace App\Services\Operations;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Appointment;
use App\Support\OrganizationContext;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(
        private readonly ActivityLogger $activities,
        private readonly ServiceRequestService $requests,
    ) {}

    public function paginate(Request $request, ?string $onlyTechnicianId = null): LengthAwarePaginator
    {
        $day = $request->string('date')->toString();

        return Appointment::query()
            ->with(['customer:id,name', 'technician.user:id,name', 'serviceRequest:id,subject'])
            ->when($onlyTechnicianId !== null, fn ($query) => $query->where('technician_id', $onlyTechnicianId))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('technician_id'), fn ($query) => $query->where('technician_id', $request->string('technician_id')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->string('branch_id')))
            ->when($day !== '', function ($query) use ($day) {
                $timezone = OrganizationContext::current()->organization->timezone;
                $start = Carbon::parse($day, $timezone)->startOfDay()->utc();
                $end = Carbon::parse($day, $timezone)->endOfDay()->utc();
                $query->whereBetween('scheduled_start', [$start, $end]);
            })
            ->orderBy('scheduled_start')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Appointment
    {
        $this->guardSchedule($data['technician_id'], $data['scheduled_start'], $data['scheduled_end'], $data['status'] ?? AppointmentStatus::Scheduled->value);

        return DB::transaction(function () use ($data) {
            $appointment = Appointment::query()->create($data);

            if ($appointment->service_request_id) {
                $request = $appointment->serviceRequest;
                if ($request && in_array($request->status, [ServiceRequestStatus::New, ServiceRequestStatus::Qualified], true)) {
                    $this->requests->mark($request, ServiceRequestStatus::Scheduled);
                }
            }

            $appointment->load('technician.user', 'customer');
            $technicianName = $appointment->technician?->user?->name;

            $this->activities->log($appointment, 'appointment.scheduled', $appointment->customer->name, [
                'technician_id' => $appointment->technician_id,
                'technician_name' => $technicianName,
            ]);

            if ($appointment->serviceRequest) {
                $this->activities->log($appointment->serviceRequest, 'technician.assigned', $technicianName, [
                    'technician_id' => $appointment->technician_id,
                    'appointment_id' => $appointment->id,
                ]);
            }

            return $appointment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Appointment $appointment, array $data): Appointment
    {
        $this->guardSchedule(
            $data['technician_id'],
            $data['scheduled_start'],
            $data['scheduled_end'],
            $data['status'],
            $appointment->id,
        );

        $appointment->update($data);

        return $appointment;
    }

    public function guardSchedule(string $technicianId, mixed $start, mixed $end, string $status, ?string $ignoreId = null): void
    {
        if ($status === AppointmentStatus::Cancelled->value) {
            return;
        }

        $starts = Carbon::parse($start);
        $ends = Carbon::parse($end);

        if ($ends->lessThanOrEqualTo($starts)) {
            throw ValidationException::withMessages([
                'scheduled_end' => __('The appointment must end after it starts.'),
            ]);
        }

        $conflict = Appointment::query()
            ->where('technician_id', $technicianId)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('scheduled_start', '<', $ends)
            ->where('scheduled_end', '>', $starts)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'scheduled_start' => __('This technician already has an appointment in that time range.'),
            ]);
        }
    }
}
