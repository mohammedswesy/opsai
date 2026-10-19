<?php

namespace App\Services\Operations;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(Model $subject, string $action, ?string $description = null, array $properties = []): Activity
    {
        $actor = auth()->user();

        return Activity::query()->create([
            'actor_id' => $actor?->getKey(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'description' => $description,
            'properties' => [
                'actor_name' => $actor?->name,
                'resource_type' => $subject->getMorphClass(),
                'resource_id' => $subject->getKey(),
                ...$properties,
            ],
        ]);
    }
}
