<?php

namespace App\Services;

use App\Mail\InvitationCreated;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationService
{
    /**
     * Create a user, its invitation, and email the activation link.
     * A temporary password is only sent for administrator-created accounts.
     *
     * @param  array{name: string, email: string, role: string, is_active?: bool, avatar?: string|null}  $data
     * @return array{user: User, invitation: Invitation, token: string}
     */
    public function create(array $data, ?string $temporaryPassword, ?User $invitedBy): array
    {
        $this->ensureRoleIsAssignable($data['role'], $invitedBy);

        $token = Str::random(64);
        $expiresAt = now()->addHours((int) config('activation.expiry_hours', 72));

        return DB::transaction(function () use ($data, $temporaryPassword, $invitedBy, $token, $expiresAt) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword ?? Str::password(32)),
                'is_active' => $data['is_active'] ?? true,
                'avatar' => $data['avatar'] ?? null,
                'email_verified_at' => null,
            ]);

            $user->syncRoles([$data['role']]);

            $invitation = Invitation::updateOrCreate(
                ['email' => $user->email],
                [
                    'name' => $user->name,
                    'role' => $data['role'],
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => $expiresAt,
                    'accepted_at' => null,
                    'invited_by' => $invitedBy?->id,
                ],
            );

            $this->sendAfterCommit($invitation, $token, $temporaryPassword);

            return ['user' => $user, 'invitation' => $invitation, 'token' => $token];
        });
    }

    /**
     * Rotate the token of a pending invitation and email the new link.
     */
    public function resend(Invitation $invitation): string
    {
        $token = Str::random(64);
        $expiresAt = now()->addHours((int) config('activation.expiry_hours', 72));

        DB::transaction(function () use ($invitation, $token, $expiresAt) {
            $lockedInvitation = Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedInvitation->isAccepted()) {
                throw ValidationException::withMessages([
                    'message' => 'Undangan sudah diterima dan tidak dapat dikirim ulang.',
                ]);
            }

            $lockedInvitation->forceFill([
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
                'accepted_at' => null,
            ])->save();

            $this->sendAfterCommit($lockedInvitation, $token, null);
        });

        return $token;
    }

    /**
     * Validate the role against the central assignability rule.
     */
    public function ensureRoleIsAssignable(string $role, ?User $actor): void
    {
        if (! in_array($role, Roles::assignableBy($actor), true)) {
            throw ValidationException::withMessages([
                'role' => 'Peran yang dipilih tidak diizinkan.',
            ]);
        }
    }

    private function sendAfterCommit(Invitation $invitation, string $token, ?string $temporaryPassword): void
    {
        DB::afterCommit(function () use ($invitation, $token, $temporaryPassword): void {
            try {
                Mail::to($invitation->email)->send(new InvitationCreated($invitation, $token, $temporaryPassword));
            } catch (Throwable $exception) {
                // The account already exists; the administrator can resend the invitation.
                Log::error('Invitation email could not be sent.', [
                    'invitation_id' => $invitation->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
