<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;

abstract class ApplicationTool implements AITool
{
    public function forStaff(): bool
    {
        return true;
    }

    public function forCustomer(): bool
    {
        return false;
    }

    public function authorizeCustomer(AIToolContext $context): bool
    {
        return false;
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function preview(array $arguments): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function ok(array $data, string $source): array
    {
        return [
            'success' => true,
            'data' => $data,
            'source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function objectSchema(array $properties, array $required = []): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'all_branches' => ['type' => 'boolean'],
                ...$properties,
            ],
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
