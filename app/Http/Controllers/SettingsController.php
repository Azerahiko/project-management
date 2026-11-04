<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passkeys\Passkey;

class SettingsController extends Controller
{
    /**
     * Display the personal settings page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize(Permissions::SETTINGS_VIEW);

        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'email_verified' => $user->email_verified_at !== null,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'passkeys_count' => Passkey::query()
                    ->where('user_id', $user->id)
                    ->count(),
                'member_since' => $user->created_at?->toISOString(),
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'notification_preferences' => $user->notificationPreferences(),
            'notification_options' => [
                'task_updates' => [
                    'label' => 'Pembaruan tugas',
                    'description' => 'Kabar saat status, tenggat, atau detail tugas berubah.',
                    'group' => 'Aktivitas Utama',
                    'icon' => 'Bell',
                ],
                'deadline_reminders' => [
                    'label' => 'Pengingat tenggat waktu',
                    'description' => 'Peringatan saat tugas mendekati batas akhir pengerjaan.',
                    'group' => 'Aktivitas Utama',
                    'icon' => 'Clock',
                ],
                'daily_digest' => [
                    'label' => 'Ringkasan harian',
                    'description' => 'Rangkuman progres pekerjaan harian di ruang kerja.',
                    'group' => 'Aktivitas Utama',
                    'icon' => 'FileText',
                ],
                'mentions' => [
                    'label' => 'Sebutan (@mention)',
                    'description' => 'Pemberitahuan saat rekan tim menandai atau membalas Anda.',
                    'group' => 'Interaksi & Suara',
                    'icon' => 'AtSign',
                ],
                'comments' => [
                    'label' => 'Aktivitas kolaborasi tim',
                    'description' => 'Diskusi dan aktivitas terbaru pada proyek yang Anda ikuti.',
                    'group' => 'Interaksi & Suara',
                    'icon' => 'MessageSquare',
                ],
                'sound' => [
                    'label' => 'Bunyi notifikasi',
                    'description' => 'Mainkan suara lembut saat pemberitahuan baru diterima.',
                    'group' => 'Interaksi & Suara',
                    'icon' => 'Volume2',
                ],
            ],
        ]);
    }

    /**
     * Update the authenticated user's profile information.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::SETTINGS_VIEW);

        $user = $request->user();

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email sudah digunakan oleh akun lain.',
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ])->save();

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::SETTINGS_VIEW);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi saat ini tidak sesuai.',
            ]);
        }

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }

    /**
     * Update the authenticated user's notification preferences.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::SETTINGS_VIEW);

        /** @var User $user */
        $user = $request->user();
        $allowed = array_keys(User::defaultNotificationPreferences());

        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ], [
            'preferences.required' => 'Preferensi notifikasi wajib diisi.',
            'preferences.array' => 'Format preferensi notifikasi tidak valid.',
            'preferences.*.boolean' => 'Nilai preferensi notifikasi tidak valid.',
        ]);

        $preferences = [];
        foreach ($allowed as $key) {
            if (isset($validated['preferences'][$key])) {
                $preferences[$key] = (bool) $validated['preferences'][$key];
            }
        }

        $user->forceFill([
            'notification_preferences' => array_merge(
                $user->notificationPreferences(),
                $preferences,
            ),
        ])->save();

        return back()->with('success', 'Preferensi notifikasi berhasil disimpan.');
    }
}
