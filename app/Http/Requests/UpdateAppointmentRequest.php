<?php

namespace App\Http\Requests;

class UpdateAppointmentRequest extends StoreAppointmentRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment !== null && ($this->user()?->can('update', $appointment) ?? false);
    }
}
