<?php

namespace App\Http\Requests;

class UpdateServiceRequestForm extends StoreServiceRequestForm
{
    public function authorize(): bool
    {
        $serviceRequest = $this->route('service_request');

        return $serviceRequest !== null && ($this->user()?->can('update', $serviceRequest) ?? false);
    }
}
