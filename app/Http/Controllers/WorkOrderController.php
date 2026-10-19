<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Concerns\SharesLookups;
use App\Http\Requests\AssignWorkOrderRequest;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Http\Requests\TransitionWorkOrderRequest;
use App\Models\Invoice;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Services\Operations\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    use SharesLookups;

    public function index(Request $request, WorkOrderService $workOrders): Response
    {
        $this->authorize('viewAny', WorkOrder::class);

        return Inertia::render('WorkOrders/Index', [
            'workOrders' => $workOrders->paginate($request, $this->ownTechnicianId($request)),
            'filters' => $request->only(['search', 'status', 'priority', 'branch_id']),
            'branches' => $this->branchOptions(),
            'canManage' => $request->user()?->can('create', WorkOrder::class) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', WorkOrder::class);

        return Inertia::render('WorkOrders/Form', [
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'technicians' => $this->technicianOptions(),
            'requests' => $this->requestOptions(),
            'defaults' => [
                'service_request_id' => $request->string('service_request_id')->toString(),
                'customer_id' => $request->string('customer_id')->toString(),
                'appointment_id' => $request->string('appointment_id')->toString(),
            ],
        ]);
    }

    public function store(StoreWorkOrderRequest $request, WorkOrderService $workOrders): RedirectResponse
    {
        $workOrder = $workOrders->create($request->validated());

        return redirect()->route('work-orders.show', $workOrder)->with('success', __('Work order saved.'));
    }

    public function show(WorkOrder $workOrder): Response
    {
        $this->authorize('view', $workOrder);
        $workOrder->load(['customer', 'technician.user:id,name', 'serviceRequest', 'appointment', 'branch:id,name']);
        $invoice = Invoice::query()
            ->where('work_order_id', $workOrder->id)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->first(['id', 'invoice_number']);

        return Inertia::render('WorkOrders/Show', [
            'workOrder' => $workOrder,
            'invoice' => $invoice,
            'transitions' => array_map(
                fn (WorkOrderStatus $status) => $status->value,
                $workOrder->status->allowedTransitions(),
            ),
            'canTransition' => request()->user()?->can('transition', $workOrder) ?? false,
            'canInvoice' => request()->user()?->can('create', Invoice::class) ?? false,
        ]);
    }

    public function transition(TransitionWorkOrderRequest $request, WorkOrder $workOrder, WorkOrderService $workOrders): RedirectResponse
    {
        $workOrders->transition(
            $workOrder,
            WorkOrderStatus::from($request->validated('status')),
            $request->validated('notes'),
        );

        return redirect()->route('work-orders.show', $workOrder)->with('success', __('Work order updated.'));
    }

    public function assign(AssignWorkOrderRequest $request, WorkOrder $workOrder, WorkOrderService $workOrders): RedirectResponse
    {
        $workOrders->assign($workOrder, $request->validated('technician_id'));

        return back()->with('success', __('Technician assigned.'));
    }

    private function ownTechnicianId(Request $request): ?string
    {
        if ($request->user()?->can('create', WorkOrder::class)) {
            return null;
        }

        return Technician::query()->where('user_id', $request->user()->id)->value('id') ?? '';
    }
}
