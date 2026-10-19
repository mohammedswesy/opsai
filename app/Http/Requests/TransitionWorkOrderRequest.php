<?php

namespace App\Http\Requests;

use App\Enums\WorkOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workOrder = $this->route('work_order');

        return $workOrder !== null && ($this->user()?->can('transition', $workOrder) ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(WorkOrderStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
