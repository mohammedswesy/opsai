<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesLookups;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Services\Operations\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    use SharesLookups;

    public function index(Request $request, TaskService $tasks): Response
    {
        $this->authorize('viewAny', Task::class);

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks->paginate($request),
            'filters' => $request->only(['search', 'status', 'priority', 'branch_id']),
            'branches' => $this->branchOptions(),
            'canManage' => $request->user()?->can('create', Task::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Task::class);

        return Inertia::render('Tasks/Form', [
            'task' => null,
            'branches' => $this->branchOptions(),
            'members' => $this->memberOptions(),
        ]);
    }

    public function store(StoreTaskRequest $request, TaskService $tasks): RedirectResponse
    {
        $tasks->create($request->validated());

        return redirect()->route('tasks.index')->with('success', __('Task saved.'));
    }

    public function edit(Task $task): Response
    {
        $this->authorize('update', $task);

        return Inertia::render('Tasks/Form', [
            'task' => $task,
            'branches' => $this->branchOptions(),
            'members' => $this->memberOptions(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task, TaskService $tasks): RedirectResponse
    {
        $tasks->update($task, $request->validated());

        return redirect()->route('tasks.index')->with('success', __('Task saved.'));
    }
}
