<?php

namespace App\Http\Controllers;

use App\Mail\InvitationCreated;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Store a new invitation (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', 'unique:invitations,email'],
            'role' => ['required', 'string', Rule::in(Roles::slugs())],
        ]);

        $expiresAt = now()->addHours(config('activation.expiry_hours', 72));
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $invitationData = DB::transaction(function () use ($validated, $expiresAt, $tokenHash, $request, $token) {
            $temporaryPassword = Str::password(32);
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'is_active' => true,
                'email_verified_at' => null,
            ]);

            $user->syncRoles([$validated['role']]);

            $invitation = Invitation::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'invited_by' => $request->user()?->id,
            ]);

            // Dispatch email after transaction commits
            $invitation->token = $token;
            $temporaryPassword = Str::password(32);
            Mail::to($validated['email'])->send(new InvitationCreated($invitation, $token, $temporaryPassword));

            return [
                'user' => $user,
                'invitation' => $invitation,
                'token' => $token,
            ];
        });

        return response()->json([
            'message' => 'Undangan berhasil dikirim.',
            'user' => [
                'id' => $invitationData['user']->id,
                'name' => $invitationData['user']->name,
                'email' => $invitationData['user']->email,
                'role' => $invitationData['invitation']->role,
            ],
        ], 201);
    }

    /**
     * Show the activation page.
     */
    public function show(string $token): Response
    {
        $invitation = Invitation::findByToken($token);

        if (! $invitation) {
            abort(404, 'Undangan tidak ditemukan.');
        }

        if ($invitation->isAccepted()) {
            abort(410, 'Undangan ini sudah digunakan.');
        }

        if ($invitation->isExpired()) {
            abort(410, 'Undangan sudah kedaluwarsa.');
        }

        return inertia('Auth/ActivateAccount', [
            'token' => $token,
            'email' => $invitation->email,
            'name' => $invitation->name,
        ]);
    }

    /**
     * Verify invitation and user email.
     */
    public function activate(Request $request, string $token): JsonResponse
    {
        // Use a single transaction for the entire invitation verification flow
        return DB::transaction(function () use ($token) {
            // Find and lock the invitation
            $invitation = Invitation::findByToken($token);

            if (! $invitation || $invitation->isAccepted() || $invitation->isExpired()) {
                return response()->json(['message' => 'Undangan tidak valid atau sudah kedaluwarsa.'], 410);
            }

            // Lock user row for update to prevent race conditions
            $user = User::where('email', $invitation->email)
                ->lockForUpdate()
                ->firstOrFail();

            // Verify email and accept invitation atomically
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $invitation->forceFill([
                'accepted_at' => now(),
            ])->save();

            return response()->json([
                'message' => 'Email dan undangan berhasil diverifikasi. Silakan masuk menggunakan kata sandi sementara Anda.',
                'redirect' => route('login'),
            ]);
        });
    }

    /**
     * Resend invitation with new token (admin only).
     */
    public function resend(Request $request, Invitation $invitation): JsonResponse
    {
        if (! $request->user()?->can('users.create')) {
            abort(403, 'Unauthorized.');
        }

        // Prevent resending accepted invitations
        if ($invitation->isAccepted()) {
            return response()->json(['message' => 'Undangan sudah diterima dan tidak dapat dikirim ulang.'], 422);
        }

        // Prevent resending for already activated accounts
        $existingUser = User::where('email', $invitation->email)
            ->whereNotNull('email_verified_at')
            ->first();
        if ($existingUser) {
            return response()->json(['message' => 'Akun sudah diaktifkan. Tidak dapat mengirim ulang undangan.'], 422);
        }

        $expiresAt = now()->addHours(config('activation.expiry_hours', 72));
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        // CONCURRENT PROTECTION: Lock user row during regeneration
        DB::transaction(function () use ($invitation, $expiresAt, $tokenHash, $token) {
            $user = User::where('email', $invitation->email)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check invitation state inside transaction
            $invitation->refresh();
            if ($invitation->isAccepted()) {
                throw ValidationException::withMessages([
                    'message' => 'Undangan sudah diterima dan tidak dapat dikirim ulang.',
                ]);
            }

            // Update invitation with new token + expiry
            $invitation->forceFill([
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'accepted_at' => null,
            ])->save();

            // Dispatch email after transaction commits
            $invitation->token = $token;
            $temporaryPassword = Str::password(32);
            $user->update(['password' => Hash::make($temporaryPassword)]);
            Mail::to($invitation->email)->send(new InvitationCreated($invitation, $token, $temporaryPassword));
        });

        return response()->json([
            'message' => 'Undangan dikirim ulang dengan tautan baru.',
        ]);
    }
}
