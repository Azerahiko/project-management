<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->validateActivationConfig();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Validate activation configuration early so bad values fail clearly.
     */
    protected function validateActivationConfig(): void
    {
        $length = (int) config('activation.code_length', 8);
        $expiry = (int) config('activation.expiry_hours', 72);
        $attempts = (int) config('activation.max_attempts', 5);

        if ($length < 6 || $length > 10) {
            throw new \RuntimeException(
                'Invalid activation.code_length: must be integer between 6 and 10.'
            );
        }

        if ($expiry < 1) {
            throw new \RuntimeException(
                'Invalid activation.expiry_hours: must be a positive integer.'
            );
        }

        if ($attempts < 1) {
            throw new \RuntimeException(
                'Invalid activation.max_attempts: must be a positive integer.'
            );
        }
    }
}
