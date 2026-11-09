<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class OnboardingController extends Controller
{
    /**
     * Show the onboarding index (dispatcher to correct step).
     */
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        $step = $user->onboardingStep();

        if ($step === 'complete') {
            return redirect()->route('dashboard');
        }

        return redirect()->route("onboarding.{$step}");
    }

    /**
     * Show the password change step.
     */
    public function password(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_password_changed_at !== null) {
            return redirect()->route('onboarding.two-factor');
        }

        return Inertia::render('Onboarding/Password', [
            'step' => 'password',
            'nextStep' => 'two_factor',
        ]);
    }

    /**
     * Handle password change.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_password_changed_at !== null) {
            return redirect()->route('onboarding.two-factor');
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::defaults(),
            ],
        ], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'onboarding_password_changed_at' => now(),
        ])->save();

        // Set password confirmed session for 2FA operations
        session(['auth.password_confirmed_at' => time()]);

        return redirect()->route('onboarding.two-factor')
            ->with('success', 'Kata sandi berhasil diubah. Silakan aktifkan autentikasi dua faktor.');
    }

    /**
     * Show the two-factor authentication step.
     */
    public function twoFactor(Request $request, EnableTwoFactorAuthentication $enable): InertiaResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_password_changed_at === null) {
            return redirect()->route('onboarding.password');
        }

        if ($user->two_factor_confirmed_at !== null && ! session('recoveryCodes')) {
            return redirect()->route('onboarding.passkey');
        }

        // Generate secret and recovery codes if not set
        if (! $user->two_factor_secret) {
            $enable($user);
        }

        $secret = decrypt($user->two_factor_secret);
        $qrCodeSvg = $user->twoFactorQrCodeSvg();

        return Inertia::render('Onboarding/TwoFactor', [
            'step' => 'two_factor',
            'nextStep' => 'passkey',
            'qrCodeSvg' => $qrCodeSvg,
            'qrCodeUrl' => sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s', rawurlencode(config('app.name')), rawurlencode($user->email), $secret, rawurlencode(config('app.name'))),
            'secret' => $secret,
            'twoFactorEnabled' => (bool) $user->two_factor_confirmed_at,
            'recoveryCodes' => session('recoveryCodes', []),
        ]);
    }

    /**
     * Confirm two-factor authentication.
     */
    public function confirmTwoFactor(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_password_changed_at === null) {
            return redirect()->route('onboarding.password');
        }

        if ($user->two_factor_confirmed_at !== null) {
            return redirect()->route('onboarding.passkey');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => 'Kode verifikasi wajib diisi.',
            'code.size' => 'Kode verifikasi harus 6 digit.',
        ]);

        $confirm($user, $validated['code']);

        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];

        return redirect()->route('onboarding.two-factor')
            ->with('recoveryCodes', $recoveryCodes)
            ->with('success', 'Autentikasi dua faktor berhasil dikonfirmasi.');
    }

    /**
     * Show the passkey step.
     */
    public function passkey(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_password_changed_at === null) {
            return redirect()->route('onboarding.password');
        }

        if ($user->two_factor_confirmed_at === null) {
            return redirect()->route('onboarding.two-factor');
        }

        return Inertia::render('Onboarding/Passkey', [
            'step' => 'passkey',
            'nextStep' => 'complete',
            'passkeys' => $user->passkeys()->select(['id', 'name', 'last_used_at', 'created_at'])->get(),
        ]);
    }

    /**
     * Skip passkey registration.
     */
    public function skipPasskey(Request $request): RedirectResponse
    {
        return $this->complete($request);
    }

    /**
     * Complete onboarding.
     */
    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_password_changed_at === null) {
            return redirect()->route('onboarding.password');
        }

        if ($user->two_factor_confirmed_at === null) {
            return redirect()->route('onboarding.two-factor');
        }

        $user->forceFill([
            'onboarding_completed_at' => now(),
        ])->save();

        // Invalidate session and logout
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Onboarding selesai! Silakan masuk dengan kredensial baru Anda.');
    }
}
