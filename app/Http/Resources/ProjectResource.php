<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'status_color' => $project->status->color(),
            'priority' => $project->priority->value,
            'priority_label' => $project->priority->label(),
            'priority_color' => $project->priority->color(),
            'start_date' => $project->start_date?->toISOString(),
            'end_date' => $project->end_date?->toISOString(),
            'owner_id' => $project->owner_id,
            'owner' => $project->relationLoaded('owner') ? UserResource::make($project->owner)->resolve() : null,
            'members_count' => $project->relationLoaded('projectMembers') ? $project->projectMembers->count() : null,
            'members' => $project->relationLoaded('projectMembers') ? ProjectMemberResource::collection($project->projectMembers)->resolve() : null,
            'can_update' => $project->can_update,
            'can_delete' => $project->can_delete,
            'can_manage_members' => $project->can_manage_members,
            'created_at' => $project->created_at?->toISOString(),
            'updated_at' => $project->updated_at?->toISOString(),
            'deleted_at' => $project->deleted_at?->toISOString(),
        ];
    }
}
