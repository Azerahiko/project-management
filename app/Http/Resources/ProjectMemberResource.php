<?php

namespace App\Http\Resources;

use App\Models\ProjectMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectMember
 */
class ProjectMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProjectMember $developer */
        $developer = $this->resource;

        return [
            'id' => $developer->id,
            'project_id' => $developer->project_id,
            'user_id' => $developer->user_id,
            'role' => $developer->role->value,
            'role_label' => $developer->role->label(),
            'joined_at' => $developer->joined_at->toISOString(),
            'user' => $developer->relationLoaded('user') ? UserResource::make($developer->user)->resolve() : null,
            'is_owner' => $developer->isOwner(),
            'created_at' => $developer->created_at?->toISOString(),
            'updated_at' => $developer->updated_at?->toISOString(),
        ];
    }
}
