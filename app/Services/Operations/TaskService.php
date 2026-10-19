<?php

namespace App\Services\Operations;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\OrganizationContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TaskService
{
    public function __construct(private readonly ActivityLogger $activities) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();

        return Task::query()
            ->with(['assignee:id,name', 'creator:id,name', 'latestActivity'])
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->string('branch_id')))
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Task
    {
        $task = Task::query()->create([
            ...$data,
            'created_by' => $data['created_by'] ?? auth()->id(),
            'status' => $data['status'] ?? TaskStatus::Todo,
        ]);

        $this->activities->log($task, 'task.created', $task->title);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        if (($data['status'] ?? null) === TaskStatus::Completed->value && $task->completed_at === null) {
            $data['completed_at'] = now();
        }

        if (($data['status'] ?? null) !== null && $data['status'] !== TaskStatus::Completed->value) {
            $data['completed_at'] = null;
        }

        $wasCompleted = $task->status === TaskStatus::Completed;
        $task->update($data);

        if (! $wasCompleted && $task->status === TaskStatus::Completed) {
            $this->activities->log($task, 'task.completed', $task->title);
        }

        return $task;
    }

    public function forCurrentOrganization(): Builder
    {
        return Task::query()->where('organization_id', OrganizationContext::id());
    }
}
