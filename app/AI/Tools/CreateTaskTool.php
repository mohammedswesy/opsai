<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\Operations\TaskService;
use App\Support\OrganizationContext;
use Carbon\Carbon;

class CreateTaskTool extends ApplicationTool
{
    public function name(): string
    {
        return 'create_task';
    }

    public function description(): string
    {
        return 'Propose a task. It is saved only after the user confirms.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'title' => ['type' => 'string'],
            'assignee_name' => ['type' => 'string'],
            'priority' => ['type' => 'string', 'enum' => Priority::values()],
            'due_on' => ['type' => 'string'],
            'description' => ['type' => 'string'],
        ], ['title']);
    }

    public function authorize(User $user): bool
    {
        return $user->can('create', Task::class);
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function preview(array $arguments): array
    {
        return [
            ['key' => 'title', 'value' => (string) ($arguments['title'] ?? '')],
            ['key' => 'assignee', 'value' => (string) ($arguments['assignee_name'] ?? '')],
            ['key' => 'priority', 'value' => (string) ($arguments['priority'] ?? Priority::Normal->value)],
            ['key' => 'due', 'value' => (string) ($arguments['due_on'] ?? '')],
        ];
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $assignee = null;
        if (is_string($arguments['assignee_name'] ?? null) && $arguments['assignee_name'] !== '') {
            $assignee = app(RecordResolver::class)->memberByName($arguments['assignee_name']);
        }

        $timezone = OrganizationContext::current()->organization->timezone;
        $due = is_string($arguments['due_on'] ?? null) && $arguments['due_on'] !== ''
            ? Carbon::parse($arguments['due_on'], $timezone)->endOfDay()->utc()
            : null;

        $task = app(TaskService::class)->create([
            'branch_id' => $context->scopedBranchId(),
            'assigned_to' => $assignee?->id,
            'title' => $arguments['title'],
            'description' => $arguments['description'] ?? null,
            'priority' => $arguments['priority'] ?? Priority::Normal->value,
            'status' => TaskStatus::Todo->value,
            'due_at' => $due,
            'created_by' => $context->user->id,
        ]);

        return $this->ok([
            'id' => $task->id,
            'title' => $task->title,
            'assignee' => $assignee?->name,
            'status' => $task->status->value,
        ], 'tasks');
    }
}
