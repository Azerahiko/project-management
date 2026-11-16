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
            ? Password::min((int) config('auth.password_min_length'))
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Validate invitation configuration early so bad values fail clearly.
     */
    protected function validateActivationConfig(): void
    {
        if ((int) config('activation.expiry_hours', 72) < 1) {
            throw new \RuntimeException(
                'Invalid activation.expiry_hours: must be a positive integer.'
            );
        }

        if ((int) config('activation.max_attempts', 5) < 1) {
            throw new \RuntimeException(
                'Invalid activation.max_attempts: must be a positive integer.'
            );
        }

        if ((int) config('activation.lockout_minutes', 15) < 1) {
            throw new \RuntimeException(
                'Invalid activation.lockout_minutes: must be a positive integer.'
            );
        }
    }
}
