<?php

namespace App\Http\Requests;

class UpdateTaskRequest extends StoreTaskRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task !== null && ($this->user()?->can('update', $task) ?? false);
    }
}
