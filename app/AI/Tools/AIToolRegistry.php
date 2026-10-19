<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use Illuminate\Validation\ValidationException;
use Throwable;

class AIToolRegistry
{
    /** @var array<string, AITool> */
    private array $tools = [];

    public function register(AITool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): ?AITool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitionsFor(AIToolContext $context): array
    {
        $definitions = [];

        foreach ($this->tools as $tool) {
            if (! $this->allows($tool, $context)) {
                continue;
            }

            $definitions[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->schema(),
                ],
            ];
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{arguments: array<string, mixed>, all_branches: bool, error: string|null}
     */
    public function prepare(AITool $tool, array $arguments): array
    {
        unset($arguments['organization_id'], $arguments['branch_id']);
        $allBranches = ($arguments['all_branches'] ?? false) === true;
        unset($arguments['all_branches']);

        return [
            'arguments' => $arguments,
            'all_branches' => $allBranches,
            'error' => $this->validate($tool->schema(), $arguments),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function allows(AITool $tool, AIToolContext $context): bool
    {
        if ($context->channel === 'customer') {
            return $tool->forCustomer() && $tool->authorizeCustomer($context);
        }

        return $context->user !== null && $tool->forStaff() && $tool->authorize($context->user);
    }

    public function execute(AITool $tool, array $arguments, AIToolContext $context): array
    {
        if (! $this->allows($tool, $context)) {
            return ['success' => false, 'data' => null, 'message' => 'You are not allowed to do that.'];
        }

        try {
            return $tool->execute($arguments, $context);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return ['success' => false, 'data' => null, 'message' => is_string($message) ? $message : 'The action could not be completed.'];
        } catch (Throwable $exception) {
            report($exception);

            return ['success' => false, 'data' => null, 'message' => 'The action could not be completed.'];
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $arguments
     */
    private function validate(array $schema, array $arguments): ?string
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($required as $field) {
            if (! array_key_exists($field, $arguments) || $arguments[$field] === '' || $arguments[$field] === null) {
                return 'Missing '.$field.'.';
            }
        }

        foreach ($arguments as $field => $value) {
            if (! array_key_exists($field, $properties)) {
                return 'Unexpected '.$field.'.';
            }

            $type = $properties[$field]['type'] ?? null;
            $enum = $properties[$field]['enum'] ?? null;

            if ($type === 'string' && ! is_string($value)) {
                return $field.' must be text.';
            }

            if ($type === 'integer' && ! is_int($value)) {
                return $field.' must be a whole number.';
            }

            if ($type === 'boolean' && ! is_bool($value)) {
                return $field.' must be true or false.';
            }

            if (is_array($enum) && ! in_array($value, $enum, true)) {
                return $field.' is not an allowed value.';
            }
        }

        return null;
    }
}
