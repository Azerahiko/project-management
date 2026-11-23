<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Response;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    /**
     * Store a new invitation (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', 'unique:invitations,email'],
            'role' => ['required', 'string', Rule::in(Roles::assignableBy($request->user()))],
        ]);

        $temporaryPassword = Str::password(16);
        $result = $this->invitations->create($validated, $temporaryPassword, $request->user());

        return response()->json([
            'message' => 'Undangan berhasil dikirim.',
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'role' => $result['invitation']->role,
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
     * Activate the invited account with a password chosen by the user.
     * The entire flow is wrapped in a single transaction with strict lock ordering
     * (Invitation first, then User) to prevent PostgreSQL deadlocks and TOCTOU races.
     */
    public function activate(Request $request, string $token): JsonResponse
    {
        return DB::transaction(function () use ($token) {
            // Lock invitation first to establish the primary lock (strict order: Invitation -> User)
            $invitation = Invitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            // Re-validate inside the locked block
            if (! $invitation || $invitation->isAccepted() || $invitation->isExpired()) {
                return response()->json(['message' => 'Undangan tidak valid atau sudah kedaluwarsa.'], 410);
            }

            // Lock user second (strict order: Invitation -> User prevents deadlocks)
            $user = User::where('email', $invitation->email)
                ->lockForUpdate()
                ->firstOrFail();

            // Mutate user state inside the lock (email verified only, password unchanged)
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            // Mutate invitation state inside the same lock
            $invitation->forceFill([
                'accepted_at' => now(),
            ])->save();

            return response()->json([
                'message' => 'Akun berhasil diverifikasi. Silakan masuk menggunakan kata sandi sementara yang dikirimkan ke email Anda.',
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

        if ($invitation->isAccepted()) {
            return response()->json(['message' => 'Undangan sudah diterima dan tidak dapat dikirim ulang.'], 422);
        }

        $existingUser = User::where('email', $invitation->email)
            ->whereNotNull('email_verified_at')
            ->first();

        if ($existingUser) {
            return response()->json(['message' => 'Akun sudah diaktifkan. Tidak dapat mengirim ulang undangan.'], 422);
        }

        $this->invitations->resend($invitation);

        return response()->json([
            'message' => 'Undangan dikirim ulang dengan tautan baru.',
        ]);
    }
}
