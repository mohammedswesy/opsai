@php
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    $separator = $rtl ? '، ' : ', ';
    $money = fn ($amount) => $format->money($amount, $invoice->currency);
    // User text goes through isolate() so mixed Arabic and English keep their word order.
    $text = fn (?string $value) => $format->isolate($value);
    $ltr = fn (?string $value) => $format->isolate($value, 'ltr');
    // Enum values ("partially_paid") read as sentences ("Partially paid") and translate through lang/ar.json.
    $label = fn (string $value) => __(ucfirst(str_replace('_', ' ', $value)));
    $legalName = $organization->legal_name ?: $organization->name;
    $orgAddress = collect([$organization->address, $organization->city])->filter()->implode($separator);
    $customer = $invoice->customer;
    $customerAddress = $customer ? collect([$customer->address, $customer->city, $customer->country])->filter()->implode($separator) : '';
    $hasDiscount = (float) $invoice->discount > 0;
@endphp
<!DOCTYPE html>
<html lang="{{ $format->locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<title>{{ $invoice->invoice_number }}</title>
<style>
    body { font-family: dejavusans; font-size: 9.5pt; color: #0f172a; line-height: 1.45; }
    h1 { font-size: 18pt; margin: 0; color: #0f172a; }
    .muted { color: #475569; }
    .small { font-size: 8.5pt; }
    .caption { font-size: 8pt; color: #64748b; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; }
    .head td { vertical-align: top; }
    .meta td { padding: 2pt 0; }
    .items { margin-top: 14pt; }
    .items th { font-size: 8pt; color: #64748b; font-weight: bold; border-bottom: 1pt solid #cbd5e1; padding: 6pt 5pt; }
    .items td { border-bottom: 0.5pt solid #e2e8f0; padding: 6pt 5pt; vertical-align: top; }
    .num { text-align: {{ $end }}; white-space: nowrap; }
    .totals td { padding: 3pt 5pt; }
    .totals .strong td { font-weight: bold; border-top: 1pt solid #cbd5e1; }
    .totals .due td { font-weight: bold; font-size: 11pt; background-color: #eff6ff; }
    .status { font-weight: bold; padding: 2pt 6pt; background-color: #f1f5f9; }
    .section { margin-top: 16pt; }
</style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 55%; text-align: {{ $start }};">
                <div style="font-size: 13pt; font-weight: bold;">{{ $text($legalName) }}</div>
                @if ($organization->legal_name && $organization->legal_name !== $organization->name)
                    <div class="muted small">{{ $text($organization->name) }}</div>
                @endif
                @if ($orgAddress !== '')
                    <div class="muted small">{{ $text($orgAddress) }}</div>
                @endif
                @if ($organization->tax_number)
                    <div class="small">{{ __('Tax number') }}: {{ $ltr($organization->tax_number) }}</div>
                @endif
                @if ($organization->phone)
                    <div class="muted small">{{ $ltr($organization->phone) }}</div>
                @endif
                @if ($organization->email)
                    <div class="muted small">{{ $ltr($organization->email) }}</div>
                @endif
                @if ($invoice->branch)
                    <div class="muted small">{{ $text($invoice->branch->name) }}</div>
                @endif
            </td>
            <td style="width: 45%; text-align: {{ $end }};">
                <h1>{{ __('Invoice') }}</h1>
                <div style="font-size: 11pt; font-weight: bold;">{{ $ltr($invoice->invoice_number) }}</div>
                <table class="meta" style="margin-top: 6pt;">
                    <tr>
                        <td class="muted small" style="text-align: {{ $start }};">{{ __('Issue date') }}</td>
                        <td class="small" style="text-align: {{ $end }};">{{ $format->date($invoice->issue_date) }}</td>
                    </tr>
                    @if ($invoice->due_date)
                        <tr>
                            <td class="muted small" style="text-align: {{ $start }};">{{ __('Due date') }}</td>
                            <td class="small" style="text-align: {{ $end }};">{{ $format->date($invoice->due_date) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="muted small" style="text-align: {{ $start }};">{{ __('Status') }}</td>
                        <td class="small" style="text-align: {{ $end }};"><span class="status">{{ $label($invoice->status->value) }}</span></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="caption">{{ __('Bill to') }}</div>
        @if ($customer)
            <div style="font-weight: bold;">{{ $text($customer->name) }}</div>
            @if ($customerAddress !== '')
                <div class="muted small">{{ $text($customerAddress) }}</div>
            @endif
            @if ($customer->phone)
                <div class="muted small">{{ $ltr($customer->phone) }}</div>
            @endif
            @if ($customer->email)
                <div class="muted small">{{ $ltr($customer->email) }}</div>
            @endif
        @else
            <div class="muted">{{ __('None') }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="text-align: {{ $start }};">{{ __('Description') }}</th>
                <th class="num">{{ __('Quantity') }}</th>
                <th class="num">{{ __('Unit price') }}</th>
                <th class="num">{{ __('Tax') }}</th>
                <th class="num">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td style="text-align: {{ $start }};">
                        {{ $text($item->description) }}
                        @if ($item->service)
                            <div class="muted small">{{ $text($item->service->name) }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $format->number($item->quantity) }}</td>
                    <td class="num">{{ $money($item->unit_price) }}</td>
                    <td class="num">{{ $money($item->tax) }}</td>
                    <td class="num">{{ $money($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 10pt;">
        <tr>
            <td style="width: 50%; vertical-align: top; text-align: {{ $start }};">
                @if ($invoice->notes)
                    <div class="caption">{{ __('Notes') }}</div>
                    @foreach (preg_split('/\R/u', $invoice->notes) as $line)
                        <div class="small">{{ $text($line) }}</div>
                    @endforeach
                @endif
            </td>
            <td style="width: 50%; vertical-align: top;">
                <table class="totals">
                    <tr><td style="text-align: {{ $start }};">{{ __('Subtotal') }}</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
                    @if ($hasDiscount)
                        <tr><td style="text-align: {{ $start }};">{{ __('Discount') }}</td><td class="num">{{ $money($invoice->discount) }}</td></tr>
                    @endif
                    <tr><td style="text-align: {{ $start }};">{{ __('Tax') }}</td><td class="num">{{ $money($invoice->tax) }}</td></tr>
                    <tr class="strong"><td style="text-align: {{ $start }};">{{ __('Total') }}</td><td class="num">{{ $money($invoice->total) }}</td></tr>
                    <tr><td style="text-align: {{ $start }};">{{ __('Amount paid') }}</td><td class="num">{{ $money($invoice->amount_paid) }}</td></tr>
                    <tr class="due"><td style="text-align: {{ $start }};">{{ __('Outstanding') }}</td><td class="num">{{ $money($invoice->amount_due) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="caption">{{ __('Payments') }}</div>
        @if ($invoice->payments->isEmpty())
            <div class="muted small">{{ __('No payments recorded.') }}</div>
        @else
            <table class="items" style="margin-top: 4pt;">
                <thead>
                    <tr>
                        <th style="text-align: {{ $start }};">{{ __('Payment date') }}</th>
                        <th style="text-align: {{ $start }};">{{ __('Payment method') }}</th>
                        <th style="text-align: {{ $start }};">{{ __('Status') }}</th>
                        <th class="num">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->payments->sortBy('paid_at') as $payment)
                        <tr>
                            <td style="text-align: {{ $start }};">{{ $format->dateTime($payment->paid_at) }}</td>
                            <td style="text-align: {{ $start }};">{{ $label($payment->method->value) }}</td>
                            <td style="text-align: {{ $start }};">{{ $label($payment->status->value) }}</td>
                            <td class="num">{{ $money($payment->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
