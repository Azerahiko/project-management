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
        /** @var ProjectMember $member */
        $member = $this->resource;

        return [
            'id' => $member->id,
            'project_id' => $member->project_id,
            'user_id' => $member->user_id,
            'role' => $member->role->value,
            'role_label' => $member->role->label(),
            'joined_at' => $member->joined_at->toISOString(),
            'user' => $member->relationLoaded('user') ? UserResource::make($member->user)->resolve() : null,
            'is_owner' => $member->isOwner(),
            'created_at' => $member->created_at?->toISOString(),
            'updated_at' => $member->updated_at?->toISOString(),
        ];
    }
}
