<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesLookups;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Technician;
use App\Services\Operations\AppointmentService;
use App\Support\OrganizationContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    use SharesLookups;

    public function index(Request $request, AppointmentService $appointments): Response
    {
        $this->authorize('viewAny', Appointment::class);

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments->paginate($request, $this->ownTechnicianId($request)),
            'filters' => $request->only(['date', 'status', 'technician_id', 'branch_id']),
            'branches' => $this->branchOptions(),
            'technicians' => $this->technicianOptions(),
            'canManage' => $request->user()?->can('create', Appointment::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Appointment::class);

        return Inertia::render('Appointments/Form', [
            'appointment' => null,
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'technicians' => $this->technicianOptions(),
            'requests' => $this->requestOptions(),
        ]);
    }

    public function store(StoreAppointmentRequest $request, AppointmentService $appointments): RedirectResponse
    {
        $appointments->create($this->normalizeTimes($request->validated()));

        return redirect()->route('appointments.index')->with('success', __('Appointment saved.'));
    }

    public function edit(Appointment $appointment): Response
    {
        $this->authorize('update', $appointment);

        $timezone = OrganizationContext::current()->organization->timezone;

        return Inertia::render('Appointments/Form', [
            'appointment' => [
                ...$appointment->toArray(),
                'scheduled_start' => $appointment->scheduled_start->timezone($timezone)->format('Y-m-d\TH:i'),
                'scheduled_end' => $appointment->scheduled_end->timezone($timezone)->format('Y-m-d\TH:i'),
            ],
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'technicians' => $this->technicianOptions(),
            'requests' => $this->requestOptions(),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $appointments->update($appointment, $this->normalizeTimes($request->validated()));

        return redirect()->route('appointments.index')->with('success', __('Appointment saved.'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeTimes(array $data): array
    {
        $timezone = OrganizationContext::current()->organization->timezone;
        $data['scheduled_start'] = Carbon::parse($data['scheduled_start'], $timezone)->utc();
        $data['scheduled_end'] = Carbon::parse($data['scheduled_end'], $timezone)->utc();

        return $data;
    }

    private function ownTechnicianId(Request $request): ?string
    {
        if ($request->user()?->can('create', Appointment::class)) {
            return null;
        }

        return Technician::query()->where('user_id', $request->user()->id)->value('id') ?? '';
    }
}
