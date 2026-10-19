<?php

namespace App\Http\Requests;

class UpdateInvoiceRequest extends StoreInvoiceRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice !== null && ($this->user()?->can('update', $invoice) ?? false);
    }
}
