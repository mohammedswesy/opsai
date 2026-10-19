<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesLookups;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Finance\CustomerBalanceService;
use App\Services\Operations\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    use SharesLookups;

    public function index(Request $request, CustomerService $customers): Response
    {
        $this->authorize('viewAny', Customer::class);

        return Inertia::render('Customers/Index', [
            'customers' => $customers->paginate($request),
            'filters' => $request->only(['search', 'status', 'type', 'branch_id', 'sort', 'direction']),
            'branches' => $this->branchOptions(),
            'canManage' => $request->user()?->can('create', Customer::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('Customers/Form', [
            'customer' => null,
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(StoreCustomerRequest $request, CustomerService $customers): RedirectResponse
    {
        $customer = $customers->create($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', __('Customer saved.'));
    }

    public function show(Customer $customer, CustomerBalanceService $balances): Response
    {
        $this->authorize('view', $customer);
        $canViewFinance = request()->user()?->can('viewAny', Invoice::class) ?? false;

        return Inertia::render('Customers/Show', [
            'customer' => $customer->load('branch:id,name'),
            'requests' => $customer->serviceRequests()->latest('requested_at')->limit(20)->get(),
            'workOrders' => $customer->workOrders()->latest()->limit(20)->get(),
            'appointments' => $customer->appointments()->latest('scheduled_start')->limit(20)->get(),
            'activities' => $customer->activities()->with('actor:id,name')->latest()->limit(30)->get(),
            'finance' => $canViewFinance ? $balances->summary($customer) : null,
            'canManage' => request()->user()?->can('update', $customer) ?? false,
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $this->authorize('update', $customer);

        return Inertia::render('Customers/Form', [
            'customer' => $customer,
            'branches' => $this->branchOptions(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, CustomerService $customers): RedirectResponse
    {
        $customers->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', __('Customer saved.'));
    }

    public function destroy(Customer $customer, CustomerService $customers): RedirectResponse
    {
        $this->authorize('delete', $customer);
        $customers->archive($customer);

        return redirect()->route('customers.index')->with('success', __('Customer archived.'));
    }
}
