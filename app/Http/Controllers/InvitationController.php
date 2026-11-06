<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\ActivateAccountRequest;
use App\Mail\InvitationCreated;
use App\Models\Invitation;
use App\Models\User;
use App\Services\ActivationCodeService;
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
    public function __construct(
        protected ActivationCodeService $activationCodeService
    ) {}

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

        $activationCode = $this->activationCodeService->generate();
        $activationCodeHash = $this->activationCodeService->hash($activationCode);
        $expiresAt = now()->addHours(config('activation.expiry_hours', 72));
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $invitationData = DB::transaction(function () use ($validated, $activationCodeHash, $expiresAt, $tokenHash, $request, $token, $activationCode) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::password(32)),
                'is_active' => true,
                'email_verified_at' => null,
                'activation_code_hash' => $activationCodeHash,
                'activation_code_expires_at' => $expiresAt,
                'activation_code_attempts' => 0,
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
            $invitation->activation_code = $activationCode;
            Mail::to($validated['email'])->queue(new InvitationCreated($invitation, $token, $activationCode));

            return [
                'user' => $user,
                'invitation' => $invitation,
                'activation_code' => $activationCode,
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
            'activation_code' => $invitationData['activation_code'],
            'temp_password' => $invitationData['activation_code'],
            'invitation_url' => route('invitations.show', ['token' => $invitationData['invitation']->token_hash]),
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
     * Activate account with activation code and set password.
     */
    public function activate(ActivateAccountRequest $request, string $token): JsonResponse
    {
        $ip = $request->ip();

        // Use a single transaction for the entire activation flow
        return DB::transaction(function () use ($request, $token, $ip) {
            // Find and lock the invitation
            $invitation = Invitation::findByToken($token);

            if (! $invitation || $invitation->isAccepted() || $invitation->isExpired()) {
                return response()->json(['message' => 'Undangan tidak valid.'], 410);
            }

            // SERVER-SIDE EMAIL VALIDATION: Resolve email from persisted invitation record
            // Never trust client-submitted email (readonly field is not a security boundary)
            $persistedEmail = $invitation->email;

            // Lock user row for update to prevent race conditions
            $user = User::where('email', $persistedEmail)
                ->lockForUpdate()
                ->firstOrFail();

            // Validate activation code (email match + code dual-factor, rate limited)
            // Email validated against persisted record via user->email inside validate()
            // Rate limit identity uses persisted email ($user->email) + IP, never client input
            $requestEmail = $request->validated('email');
            $this->activationCodeService->validate($user, $requestEmail, $request->validated('activation_code'), $ip);

            // Set user's real password
            $user->forceFill([
                'password' => Hash::make($request->validated('password')),
                'email_verified_at' => now(),
            ])->save();

            // Permanently invalidate activation code (atomic within transaction)
            $this->activationCodeService->consume($user);

            // Mark invitation accepted
            $invitation->forceFill(['accepted_at' => now()])->save();

            return response()->json([
                'message' => 'Aktivasi berhasil. Silakan masuk dengan kredensial baru Anda.',
                'redirect' => route('login'),
            ]);
        });
    }

    /**
     * Resend invitation with new activation code (admin only).
     */
    public function resend(Request $request, Invitation $invitation): JsonResponse
    {
        $this->authorize('resend', $invitation);

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

        $activationCode = $this->activationCodeService->generate();
        $activationCodeHash = $this->activationCodeService->hash($activationCode);
        $expiresAt = now()->addHours(config('activation.expiry_hours', 72));
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        // CONCURRENT PROTECTION: Lock user row during regeneration
        DB::transaction(function () use ($invitation, $activationCodeHash, $expiresAt, $tokenHash, $activationCode, $token) {
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

            // Update user with NEW code (immediately invalidates previous)
            $user->forceFill([
                'activation_code_hash' => $activationCodeHash,
                'activation_code_expires_at' => $expiresAt,
                'activation_code_attempts' => 0,
            ])->save();

            // Update invitation with new token + expiry
            $invitation->forceFill([
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'accepted_at' => null,
            ])->save();

            // Dispatch email after transaction commits
            $invitation->token = $token;
            $invitation->activation_code = $activationCode;
            Mail::to($invitation->email)->queue(new InvitationCreated($invitation, $token, $activationCode));
        });

        return response()->json([
            'message' => 'Undangan dikirim ulang dengan kode baru.',
            'activation_code' => $activationCode,
            'invitation_url' => route('invitations.show', ['token' => $tokenHash]),
        ]);
    }
}
