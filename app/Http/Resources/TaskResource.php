<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Task $task */
        $task = $this->resource;

        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'status_color' => $task->status->color(),
            'priority' => $task->priority->value,
            'priority_label' => $task->priority->label(),
            'priority_color' => $task->priority->color(),
            'assignee_id' => $task->assignee_id,
            'creator_id' => $task->creator_id,
            'start_date' => $task->start_date?->toISOString(),
            'due_date' => $task->due_date?->toISOString(),
            'completed_at' => $task->completed_at?->toISOString(),
            'assignee' => $task->relationLoaded('assignee') ? UserResource::make($task->assignee)->resolve() : null,
            'creator' => $task->relationLoaded('creator') ? UserResource::make($task->creator)->resolve() : null,
            'project' => $task->relationLoaded('project') ? [
                'id' => $task->project->id,
                'name' => $task->project->name,
            ] : null,
            'can_update' => $task->can_update,
            'can_delete' => $task->can_delete,
            'is_overdue' => $task->isOverdue(),
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
            'deleted_at' => $task->deleted_at?->toISOString(),
        ];
    }
}
