<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\SharesLookups;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WorkOrder;
use App\Services\Finance\InvoiceLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use SharesLookups;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = Invoice::query()
            ->with('customer:id,name')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->toString();
                $query->where(function ($query) use ($search) {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->string('customer_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('issue_date', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('issue_date', '<=', $request->string('to')))
            ->when($request->boolean('overdue'), fn ($query) => $query->overdue())
            ->latest('issue_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'status', 'customer_id', 'from', 'to', 'overdue']),
            'customers' => $this->customerOptions(),
            'canManage' => $request->user()?->can('create', Invoice::class) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        return Inertia::render('Invoices/Form', [
            'invoice' => null,
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'services' => $this->serviceOptions(),
            'workOrders' => WorkOrder::query()->where('status', 'completed')->latest()->limit(100)->get(['id', 'title', 'customer_id']),
            'defaults' => [
                'customer_id' => $request->string('customer_id')->toString(),
                'work_order_id' => $request->string('work_order_id')->toString(),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request, InvoiceLifecycleService $invoices): RedirectResponse
    {
        $data = $request->safe()->except('items');
        $invoice = $invoices->create($data, $request->validated('items'));

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice saved.'));
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $invoice->load(['customer', 'workOrder', 'items.service:id,name', 'payments.creator:id,name', 'activities.actor:id,name', 'branch:id,name']);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'canManage' => request()->user()?->can('issue', $invoice) ?? false,
            'canPay' => request()->user()?->can('create', Payment::class) ?? false,
        ]);
    }

    public function edit(Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        return Inertia::render('Invoices/Form', [
            'invoice' => $invoice->load('items'),
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'services' => $this->serviceOptions(),
            'workOrders' => WorkOrder::query()->where('status', 'completed')->latest()->limit(100)->get(['id', 'title', 'customer_id']),
            'defaults' => ['customer_id' => '', 'work_order_id' => ''],
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, InvoiceLifecycleService $invoices): RedirectResponse
    {
        $invoices->updateDraft($invoice, $request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice saved.'));
    }

    public function issue(Invoice $invoice, InvoiceLifecycleService $invoices): RedirectResponse
    {
        $this->authorize('issue', $invoice);
        $invoices->issue($invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice issued.'));
    }

    public function cancel(Invoice $invoice, InvoiceLifecycleService $invoices): RedirectResponse
    {
        $this->authorize('cancel', $invoice);
        $invoices->cancel($invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice cancelled.'));
    }

    public function fromWorkOrder(WorkOrder $workOrder, InvoiceLifecycleService $invoices): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $existing = Invoice::query()
            ->where('work_order_id', $workOrder->id)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->exists();
        $invoice = $invoices->generateFromWorkOrder($workOrder);

        return redirect()->route('invoices.show', $invoice)->with('success', $existing ? __('Invoice already exists.') : __('Invoice saved.'));
    }
}
