<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

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
    public function twoFactor(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_password_changed_at === null) {
            return redirect()->route('onboarding.password');
        }

        if ($user->two_factor_confirmed_at !== null) {
            return redirect()->route('onboarding.passkey');
        }

        // Generate QR code and secret if not already set
        if (! $user->two_factor_secret) {
            $user->generateTwoFactorSecret();
        }

        $secret = decrypt($user->two_factor_secret);
        $qrCodeUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode(config('app.name')),
            rawurlencode($user->email),
            $secret,
            rawurlencode(config('app.name'))
        );

        return Inertia::render('Onboarding/TwoFactor', [
            'step' => 'two_factor',
            'nextStep' => 'passkey',
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $secret,
            'twoFactorEnabled' => false,
        ]);
    }

    /**
     * Confirm two-factor authentication.
     */
    public function confirmTwoFactor(Request $request): RedirectResponse
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

        // Verify the code using Fortify's mechanism
        $valid = $this->verifyTwoFactorCode($user, $validated['code']);

        if (! $valid) {
            return back()->withErrors(['code' => 'Kode verifikasi tidak valid.']);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return redirect()->route('onboarding.passkey')
            ->with('success', 'Autentikasi dua faktor aktif. Sekarang Anda bisa mendaftarkan passkey (opsional).');
    }

    /**
     * Verify a TOTP code.
     */
    protected function verifyTwoFactorCode(User $user, string $code): bool
    {
        $secret = decrypt($user->two_factor_secret);
        $google2fa = new Google2FA;

        return (bool) $google2fa->verifyKey($secret, $code);
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
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Onboarding selesai! Silakan masuk dengan kredensial baru Anda.');
    }
}
