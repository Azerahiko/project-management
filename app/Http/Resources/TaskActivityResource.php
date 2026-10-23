<?php

namespace App\Http\Resources;

use App\Models\TaskActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TaskActivity $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'task_id' => $activity->task_id,
            'user_id' => $activity->user_id,
            'activity_type' => $activity->activity_type,
            'field' => $activity->field,
            'old_value' => $activity->old_value,
            'new_value' => $activity->new_value,
            'description' => $activity->description,
            'created_at' => $activity->created_at?->toISOString(),
            'user' => $activity->relationLoaded('user') ? UserResource::make($activity->user)->resolve() : null,
        ];
    }
}
