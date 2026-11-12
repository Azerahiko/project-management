<?php

use App\Console\Commands\CleanupNotificationIdempotencyKeys;
use App\Http\Middleware\CheckTokenAbility;
use App\Http\Middleware\EnsureOnboardingIsComplete;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CleanupNotificationIdempotencyKeys::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureUserIsActive::class,
            EnsureOnboardingIsComplete::class,
        ]);
        $middleware->alias([
            'onboarding.incomplete' => EnsureOnboardingIsComplete::class,
            'token.ability' => CheckTokenAbility::class,
        ]);
        $middleware->statefulApi();
        $middleware->api(append: [
            EnsureUserIsActive::class,
            EnsureOnboardingIsComplete::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('notifications:cleanup-idempotency-keys --days=90')
            ->daily()
            ->at('02:00')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
