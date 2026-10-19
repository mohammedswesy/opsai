<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\Models\User;

interface AITool
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function schema(): array;

    public function authorize(User $user): bool;

    public function forStaff(): bool;

    public function forCustomer(): bool;

    public function authorizeCustomer(AIToolContext $context): bool;

    public function requiresConfirmation(): bool;

    /**
     * @param  array<string, mixed>  $arguments
     * @return list<array{key: string, value: string}>
     */
    public function preview(array $arguments): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, AIToolContext $context): array;
}
