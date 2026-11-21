<?php

use App\Models\Notification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Here you may register Console Commands for your application. These
| commands are typically stored in the App\Console\Commands directory.
| Most of the commands are registered in the service provider of your
| application. Don't forget to register them here so they can be
| used by the `artisan list` command.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Every day at midnight, we will prune old read notifications and
| clean up idempotency keys to prevent unbounded database growth.
*/
Schedule::call(function () {
    // Prune read notifications older than retention period (default: 60 days)
    Artisan::call('model:prune', [
        '--model' => Notification::class,
    ]);

    // Clean up old notification idempotency keys (default: 90 days)
    Artisan::call('notifications:cleanup-idempotency-keys', [
        '--days' => 90,
    ]);
})->daily();
