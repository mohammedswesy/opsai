<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'string', OrganizationRules::exists('branches', true)],
            'assigned_to' => ['nullable', 'string', OrganizationRules::member()],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(Priority::values())],
            'status' => ['required', Rule::in(TaskStatus::values())],
            'due_at' => ['nullable', 'date'],
            'related_type' => ['nullable', 'string', 'max:100'],
            'related_id' => ['nullable', 'string'],
        ];
    }
}
