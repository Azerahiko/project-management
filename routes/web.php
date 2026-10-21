<?php

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public login page (Inertia)
Route::inertia('/login', 'Auth/Login')->name('login')->middleware('guest');

// Fortify registers its own POST /login and POST /logout routes automatically.

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('/notifications', 'Notifications/Index')->name('notifications');
    Route::inertia('/projects', 'Projects/Index')->name('projects');
    Route::inertia('/tasks', 'Tasks/Index')->name('tasks');
    Route::inertia('/collaboration', 'Collaboration/Index')->name('collaboration');
    Route::inertia('/teams', 'Teams/Index')->name('teams');
    Route::inertia('/calendar', 'Calendar/Index')->name('calendar');
    Route::inertia('/documents', 'Documents/Index')->name('documents');
    Route::inertia('/reports', 'Reports/Index')->name('reports');
    Route::inertia('/activity-log', 'ActivityLog/Index')->name('activity-log');

    // Admin-only routes
    Route::middleware('can:users.view')->group(function () {
        Route::inertia('/users', 'Users/Index')->name('users.index');
        Route::inertia('/users/create', 'Users/Create')->name('users.create');
        Route::get('/users/{user}/edit', function (User $user, Request $request) {
            return Inertia::render('Users/Edit', [
                'user' => UserResource::make($user)->toArray($request),
            ]);
        })->name('users.edit');
    });

    // Settings route (accessible to all authenticated users)
    Route::middleware('can:settings.view')->group(function () {
        Route::inertia('/settings', 'Settings/Index')->name('settings');
    });
});

// Root redirects to dashboard if authenticated, else login
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});
