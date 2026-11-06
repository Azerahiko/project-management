<?php

namespace App\Http\Controllers;

use App\Mail\InvitationCreated;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        $tempPassword = Str::password(16);
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $invitationData = DB::transaction(function () use ($validated, $tempPassword, $tokenHash, $request, $token) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($tempPassword),
                'is_active' => true,
                'email_verified_at' => null,
            ]);

            $user->syncRoles([$validated['role']]);

            $invitation = Invitation::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'token_hash' => $tokenHash,
                'expires_at' => now()->addHours(config('invitations.expiry_hours', 72)),
                'invited_by' => $request->user()?->id,
            ]);

            Mail::to($validated['email'])->queue(new InvitationCreated($invitation, $token));

            return [
                'user' => $user,
                'invitation' => $invitation,
                'temp_password' => $tempPassword,
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
            'temp_password' => $invitationData['temp_password'],
            'invitation_url' => route('invitations.show', ['token' => $invitationData['invitation']->token_hash]),
        ], 201);
    }

    /**
     * Show the invitation acceptance page.
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

        return inertia('Auth/AcceptInvitation', [
            'token' => $token,
            'email' => $invitation->email,
            'name' => $invitation->name,
        ]);
    }

    /**
     * Accept the invitation (verify email).
     */
    public function accept(Request $request, string $token): RedirectResponse
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

        DB::transaction(function () use ($invitation) {
            $user = User::where('email', $invitation->email)->firstOrFail();
            $user->forceFill(['email_verified_at' => now()])->save();
            $invitation->forceFill(['accepted_at' => now()])->save();
        });

        return redirect()->route('login')->with('status', 'Email berhasil diverifikasi. Silakan masuk dengan password default Anda.');
    }
}
