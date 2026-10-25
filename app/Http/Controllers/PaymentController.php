<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesLookups;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    use SharesLookups;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        return Inertia::render('Payments/Index', [
            'payments' => Payment::query()
                ->with('invoice:id,invoice_number,customer_id')
                ->addSelect(['customer_name' => Customer::withTrashed()
                    ->select('customers.name')
                    ->join('invoices', 'invoices.customer_id', '=', 'customers.id')
                    ->whereColumn('invoices.id', 'payments.invoice_id')
                    ->limit(1)])
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->string('search')->trim()->toString();
                    $query->where(function ($query) use ($search) {
                        $query->where('reference', 'like', "%{$search}%")
                            ->orWhereHas('invoice', fn ($query) => $query->where('invoice_number', 'like', "%{$search}%"));
                    });
                })
                ->latest('paid_at')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only(['search']),
            'canManage' => $request->user()?->can('create', Payment::class) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Payment::class);

        return Inertia::render('Payments/Form', [
            'invoices' => Invoice::query()->outstanding()->orderBy('invoice_number')->get(['id', 'invoice_number', 'amount_due', 'currency']),
            'defaults' => ['invoice_id' => $request->string('invoice_id')->toString()],
        ]);
    }

    public function store(StorePaymentRequest $request, PaymentService $payments): RedirectResponse
    {
        $invoice = Invoice::query()->findOrFail($request->validated('invoice_id'));
        $payment = $payments->record($invoice, $request->safe()->except('invoice_id'));

        return redirect()->route('payments.show', $payment)->with('success', __('Payment recorded.'));
    }

    public function show(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        return Inertia::render('Payments/Show', [
            'payment' => $payment->load(['invoice.customer:id,name', 'creator:id,name']),
            'canRefund' => request()->user()?->can('refund', $payment) ?? false,
        ]);
    }

    public function refund(Payment $payment, PaymentService $payments): RedirectResponse
    {
        $this->authorize('refund', $payment);
        $payments->refund($payment);

        return redirect()->route('payments.show', $payment)->with('success', __('Payment refunded.'));
    }
}
