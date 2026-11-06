<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivationCodeService
{
    public function generate(): string
    {
        $length = $this->getCodeLength();

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    public function hash(string $code): string
    {
        return Hash::make($code);
    }

    /**
     * Validate activation code with rate limiting.
     * Rate limit checked BEFORE hash verification.
     * Uses Hash::check() exclusively for bcrypt verification.
     * Email identity validation is separate from hash verification.
     */
    public function validate(User $user, string $email, string $code, string $ip): bool
    {
        // Rate limit uses persisted email ($user->email) + IP, never client input
        $this->checkRateLimit($user->email, $ip);

        // Verify email matches (dual-factor requirement)
        // Server-side validation: email resolved from persisted record, not client input
        if (! hash_equals($user->email, $email)) {
            throw ValidationException::withMessages([
                'email' => 'Email tidak cocok dengan undangan.',
            ]);
        }

        $this->verifyCodeExistsAndNotExpired($user, $user->email, $ip);
        $this->verifyCodeHash($user, $code, $user->email, $ip);
        $this->verifyAttemptLimit($user);

        return true;
    }

    /**
     * Consume code after successful activation — permanently invalidate.
     * Called within transaction with lockForUpdate() for atomicity.
     */
    public function consume(User $user): void
    {
        $user->forceFill([
            'activation_code_hash' => null,
            'activation_code_expires_at' => null,
            'activation_code_attempts' => 0,
        ])->save();
    }

    public function maxAttempts(): int
    {
        return config('activation.max_attempts', 5);
    }

    public function lockoutMinutes(): int
    {
        return config('activation.lockout_minutes', 15);
    }

    /** Get validated code length (6-10 digits). */
    protected function getCodeLength(): int
    {
        $length = config('activation.code_length', 8);

        if (! is_int($length) || $length < 6 || $length > 10) {
            throw new \RuntimeException(
                'Invalid activation.code_length: must be integer between 6 and 10.'
            );
        }

        return $length;
    }

    /** Rate limit by IP + normalized email. */
    protected function checkRateLimit(string $email, string $ip): void
    {
        $key = $this->rateLimitKey($email, $ip);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }
    }

    /** Verify email matches persisted user record. */
    protected function verifyEmailMatch(User $user, string $email): void
    {
        if (! hash_equals($user->email, $email)) {
            throw ValidationException::withMessages([
                'email' => 'Email tidak cocok dengan undangan.',
            ]);
        }
    }

    /** Verify code exists and not expired. */
    protected function verifyCodeExistsAndNotExpired(User $user, string $email, string $ip): void
    {
        if (! $user->activation_code_hash || ! $user->activation_code_expires_at?->isFuture()) {
            $this->recordFailedAttempt($email, $ip);
            throw ValidationException::withMessages([
                'code' => 'Kode aktivasi tidak valid atau telah kedaluwarsa.',
            ]);
        }
    }

    /** Verify bcrypt hash of code. */
    protected function verifyCodeHash(User $user, string $code, string $email, string $ip): void
    {
        $valid = Hash::check($code, $user->activation_code_hash);

        if (! $valid) {
            $user->increment('activation_code_attempts');
            $this->recordFailedAttempt($email, $ip);
            throw ValidationException::withMessages([
                'code' => 'Kode aktivasi tidak valid.',
            ]);
        }
    }

    /** Verify user hasn't exceeded max attempts. */
    protected function verifyAttemptLimit(User $user): void
    {
        if ($user->activation_code_attempts >= $this->maxAttempts()) {
            throw ValidationException::withMessages([
                'code' => 'Terlalu banyak percobaan. Silakan minta kode baru.',
            ]);
        }
    }

    protected function recordFailedAttempt(string $email, string $ip): void
    {
        $key = $this->rateLimitKey($email, $ip);
        RateLimiter::hit($key, $this->lockoutMinutes() * 60);
    }

    protected function rateLimitKey(string $email, string $ip): string
    {
        return 'activation:'.Str::lower(trim($email)).':'.$ip;
    }
}
