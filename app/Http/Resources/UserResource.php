<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $this->resource;

        if (! $user) {
            return [
                'id' => null,
                'name' => null,
                'email' => null,
                'email_verified_at' => null,
                'is_active' => false,
                'avatar' => null,
                'initials' => null,
                'roles' => [],
                'permissions' => [],
                'onboarding_completed_at' => null,
                'is_onboarded' => false,
                'two_factor_enabled' => false,
                'invitation_status' => 'none',
                'invitation_id' => null,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        $invitation = $user->invitation;
        $invitationStatus = 'none';
        if ($invitation) {
            if ($invitation->isAccepted()) {
                $invitationStatus = 'accepted';
            } elseif ($invitation->isExpired()) {
                $invitationStatus = 'expired';
            } else {
                $invitationStatus = 'pending';
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'is_active' => $user->is_active,
            'avatar' => $user->avatar,
            'initials' => $user->initials,
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'onboarding_completed_at' => $user->onboarding_completed_at?->toISOString(),
            'is_onboarded' => $user->isOnboarded(),
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'invitation_status' => $invitationStatus,
            'invitation_id' => $invitation?->id,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
