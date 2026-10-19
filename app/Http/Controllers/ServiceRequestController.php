<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesLookups;
use App\Http\Requests\StoreServiceRequestForm;
use App\Http\Requests\UpdateServiceRequestForm;
use App\Models\ServiceRequest;
use App\Services\Operations\ServiceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    use SharesLookups;

    public function index(Request $request, ServiceRequestService $requests): Response
    {
        $this->authorize('viewAny', ServiceRequest::class);

        return Inertia::render('Requests/Index', [
            'requests' => $requests->paginate($request),
            'filters' => $request->only(['search', 'status', 'priority', 'source', 'branch_id']),
            'branches' => $this->branchOptions(),
            'canManage' => $request->user()?->can('create', ServiceRequest::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ServiceRequest::class);

        return Inertia::render('Requests/Form', [
            'serviceRequest' => null,
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'services' => $this->serviceOptions(),
        ]);
    }

    public function store(StoreServiceRequestForm $request, ServiceRequestService $requests): RedirectResponse
    {
        $data = $request->validated();
        $data['requested_at'] = $data['requested_at'] ?? now();
        $serviceRequest = $requests->create($data);

        return redirect()->route('requests.show', $serviceRequest)->with('success', __('Service request saved.'));
    }

    public function show(ServiceRequest $serviceRequest): Response
    {
        $this->authorize('view', $serviceRequest);
        $serviceRequest->load([
            'customer',
            'service',
            'branch:id,name',
            'appointments.technician.user:id,name',
            'workOrder.technician.user:id,name',
            'activities.actor:id,name',
        ]);

        return Inertia::render('Requests/Show', [
            'serviceRequest' => $serviceRequest,
            'canManage' => request()->user()?->can('update', $serviceRequest) ?? false,
        ]);
    }

    public function edit(ServiceRequest $serviceRequest): Response
    {
        $this->authorize('update', $serviceRequest);

        return Inertia::render('Requests/Form', [
            'serviceRequest' => $serviceRequest,
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'services' => $this->serviceOptions(),
        ]);
    }

    public function update(UpdateServiceRequestForm $request, ServiceRequest $serviceRequest, ServiceRequestService $requests): RedirectResponse
    {
        $requests->update($serviceRequest, $request->validated());

        return redirect()->route('requests.show', $serviceRequest)->with('success', __('Service request saved.'));
    }
}
