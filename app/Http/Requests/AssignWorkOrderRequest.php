<?php

namespace App\Http\Requests;

use App\Models\WorkOrder;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;

class AssignWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workOrder = $this->route('work_order');

        return $workOrder instanceof WorkOrder && ($this->user()?->can('assign', $workOrder) ?? false);
    }

    public function rules(): array
    {
        return [
            'technician_id' => ['required', 'string', OrganizationRules::exists('technicians', true)],
        ];
    }
}
