<?php

namespace App\Http\Requests;

use App\Enums\RecordStatus;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Service::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'base_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'estimated_duration' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'status' => ['required', Rule::in(RecordStatus::values())],
        ];
    }
}
