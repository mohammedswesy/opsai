<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Tools\Concerns\ScopesBranch;
use App\Models\Task;
use App\Models\User;

class GetRecentTasksTool extends ApplicationTool
{
    use ScopesBranch;

    public function name(): string
    {
        return 'get_recent_tasks';
    }

    public function description(): string
    {
        return 'The latest tasks in the current organization.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'limit' => ['type' => 'integer'],
        ]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Task::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $limit = min(10, max(1, (int) ($arguments['limit'] ?? 5)));
        $tasks = $this->scopeBranch(Task::query(), $context)
            ->with('assignee:id,name')
            ->latest()
            ->limit($limit)
            ->get();

        return $this->ok([
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status->value,
                'priority' => $task->priority->value,
                'assignee' => $task->assignee?->name,
            ])->all(),
        ], 'tasks');
    }
}
