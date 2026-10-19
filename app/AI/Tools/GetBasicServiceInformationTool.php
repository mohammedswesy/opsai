<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\Enums\RecordStatus;
use App\Models\Service;
use App\Models\User;
use App\Support\OrganizationContext;

class GetBasicServiceInformationTool extends ApplicationTool
{
    public function name(): string
    {
        return 'get_basic_service_information';
    }

    public function description(): string
    {
        return 'List active service names, descriptions, and catalog prices for this organization. Quote only these prices.';
    }

    public function schema(): array
    {
        return $this->objectSchema([]);
    }

    public function forStaff(): bool
    {
        return false;
    }

    public function forCustomer(): bool
    {
        return true;
    }

    public function authorize(User $user): bool
    {
        return false;
    }

    public function authorizeCustomer(AIToolContext $context): bool
    {
        return $context->customer !== null;
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $currency = OrganizationContext::current()->organization->currency;
        $services = Service::query()
            ->where('status', RecordStatus::Active->value)
            ->orderBy('name')
            ->limit(20)
            ->get(['name', 'description', 'base_price']);

        return $this->ok([
            'currency' => $currency,
            'services' => $services->map(fn (Service $service) => [
                'name' => $service->name,
                'description' => $service->description,
                'price' => $service->base_price !== null ? (string) $service->base_price : null,
            ])->all(),
        ], 'services');
    }
}
