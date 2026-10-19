<?php

namespace App\Http\Requests;

class UpdateCatalogItemRequest extends StoreCatalogItemRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service !== null && ($this->user()?->can('update', $service) ?? false);
    }
}
