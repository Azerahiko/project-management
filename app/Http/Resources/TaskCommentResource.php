<?php

namespace App\Http\Resources;

use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TaskComment $comment */
        $comment = $this->resource;

        return [
            'id' => $comment->id,
            'task_id' => $comment->task_id,
            'user_id' => $comment->user_id,
            'content' => $comment->content,
            'created_at' => $comment->created_at?->toISOString(),
            'updated_at' => $comment->updated_at?->toISOString(),
            'deleted_at' => $comment->deleted_at?->toISOString(),
            'user' => $comment->relationLoaded('user') ? UserResource::make($comment->user)->resolve() : null,
            'can_update' => $request->user()?->can('update', $comment) ?? false,
            'can_delete' => $request->user()?->can('delete', $comment) ?? false,
        ];
    }
}
