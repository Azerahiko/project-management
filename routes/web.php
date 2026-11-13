<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApiIntegrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\WorkspaceOverviewController;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public login page (Inertia)
Route::inertia('/login', 'Auth/Login')->name('login')->middleware('guest');

// Password reset request page (Inertia)
Route::inertia('/forgot-password', 'Auth/ForgotPassword')->name('password.request')->middleware('guest');

// Password reset form page (Inertia)
Route::get('/reset-password/{token}', function (Request $request, string $token) {
    return Inertia::render('Auth/ResetPassword', [
        'email' => (string) $request->query('email', ''),
        'token' => $token,
    ]);
})->name('password.reset')->middleware('guest');

// Two-factor challenge page (Inertia)
Route::get('/two-factor-challenge', function () {
    return Inertia::render('Auth/TwoFactorChallenge');
})->name('two-factor.login')->middleware('guest');

// Fortify registers its own POST /login and POST /logout routes automatically.

// Public invitation routes
Route::get('/invite/{token}', [InvitationController::class, 'show'])
    ->name('invitations.show')
    ->middleware(['guest', 'throttle:10,1']);

// Onboarding routes (requires auth, redirects if complete)
Route::middleware(['auth', 'onboarding.incomplete'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'index'])->name('index');
    Route::get('/password', [OnboardingController::class, 'password'])->name('password');
    Route::put('/password', [OnboardingController::class, 'updatePassword'])->name('password.update');
    Route::get('/two-factor', [OnboardingController::class, 'twoFactor'])->name('two-factor');
    Route::post('/two-factor/confirmed', [OnboardingController::class, 'confirmTwoFactor'])->name('two-factor.confirmed');
    Route::get('/passkey', [OnboardingController::class, 'passkey'])->name('passkey');
    Route::post('/passkey/skip', [OnboardingController::class, 'skipPasskey'])->name('passkey.skip');
    Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
});

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('can:dashboard.view');
    Route::inertia('/notifications', 'Notifications/Index')
        ->name('notifications')
        ->middleware('can:notifications.view');

    // Project routes
    Route::middleware('can:projects.view')->group(function () {
        Route::inertia('/projects', 'Projects/Index')->name('projects.index');
        Route::inertia('/projects/trashed', 'Projects/Trashed')->name('projects.trashed');
    });

    Route::middleware('can:projects.create')->group(function () {
        Route::inertia('/projects/create', 'Projects/Create')->name('projects.create');
    });

    Route::middleware('can:projects.view')->group(function () {
        Route::get('/projects/{project}', function (Project $project, Request $request) {
            abort_unless($request->user()->can('view', $project), 403);

            $project->load(['owner', 'projectMembers.user', 'tasks.assignee']);
            $project->can_update = $request->user()->can('update', $project);
            $project->can_delete = $request->user()->can('delete', $project);
            $project->can_manage_members = $request->user()->can('manageMembers', $project);

            return Inertia::render('Projects/Show', [
                'project' => ProjectResource::make($project)->toArray($request),
            ]);
        })->name('projects.show');
    });

    Route::middleware('can:projects.update')->group(function () {
        Route::get('/projects/{project}/edit', function (Project $project, Request $request) {
            abort_unless($request->user()->can('update', $project), 403);

            $project->load(['owner', 'projectMembers.user', 'tasks.assignee']);
            $project->can_update = $request->user()->can('update', $project);
            $project->can_delete = $request->user()->can('delete', $project);
            $project->can_manage_members = $request->user()->can('manageMembers', $project);

            return Inertia::render('Projects/Edit', [
                'project' => ProjectResource::make($project)->toArray($request),
            ]);
        })->name('projects.edit');
    });

    Route::middleware('can:tasks.view')->group(function () {
        Route::inertia('/tasks', 'Tasks/Index')->name('tasks.index');
        Route::inertia('/tasks/create', 'Tasks/Create')->name('tasks.create');
        Route::get('/projects/{project}/tasks/trashed', function (Project $project, Request $request) {
            abort_unless($request->user()->can('view', $project), 403);

            return Inertia::render('Tasks/Trashed', [
                'project' => ProjectResource::make($project->load('owner'))->toArray($request),
            ]);
        })->name('tasks.trashed');
        Route::get('/tasks/{task}', function (Task $task, Request $request) {
            abort_unless($request->user()->can('view', $task), 403);

            $task->load(['project', 'assignee', 'creator']);
            $task->can_update = $request->user()->can('update', $task);
            $task->can_delete = $request->user()->can('delete', $task);

            return Inertia::render('Tasks/Show', [
                'task' => TaskResource::make($task)->toArray($request),
            ]);
        })->name('tasks.show')->whereNumber('task');

        Route::get('/tasks/{task}/edit', function (Task $task, Request $request) {
            abort_unless($request->user()->can('update', $task), 403);

            $task->load(['project', 'assignee', 'creator']);
            $task->can_update = $request->user()->can('update', $task);
            $task->can_delete = $request->user()->can('delete', $task);

            return Inertia::render('Tasks/Edit', [
                'task' => TaskResource::make($task)->toArray($request),
            ]);
        })->name('tasks.edit');

        Route::get('/tasks/{task}/comments/trashed', function (Task $task, Request $request) {
            abort_unless($request->user()->can('view', $task), 403);

            $task->load(['project', 'assignee', 'creator']);

            return Inertia::render('TaskComments/Trashed', [
                'task' => TaskResource::make($task)->toArray($request),
            ]);
        })->name('task-comments.trashed');
    });
    Route::get('/timeline', [TimelineController::class, 'index'])
        ->name('timeline')
        ->middleware('can:projects.view');
    Route::get('/announcements', [AnnouncementController::class, 'index'])
        ->name('announcements.index')
        ->middleware('can:dashboard.view');
    Route::post('/announcements', [AnnouncementController::class, 'store'])
        ->name('announcements.store')
        ->middleware('can:teams.view');
    Route::patch('/announcements/{announcement}/pin', [AnnouncementController::class, 'togglePin'])
        ->name('announcements.pin')
        ->middleware('can:teams.view');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
        ->name('announcements.destroy')
        ->middleware('can:teams.view');
    Route::get('/collaboration', [WorkspaceOverviewController::class, 'collaboration'])
        ->name('collaboration')
        ->middleware('can:task-collaboration.view');
    Route::get('/teams', [WorkspaceOverviewController::class, 'teams'])
        ->name('teams')
        ->middleware('can:teams.view');
    Route::get('/calendar', [WorkspaceOverviewController::class, 'calendar'])
        ->name('calendar')
        ->middleware('can:calendar.view');
    Route::get('/documents', [WorkspaceOverviewController::class, 'documents'])
        ->name('documents')
        ->middleware('can:documents.view');
    Route::get('/reports', [WorkspaceOverviewController::class, 'reports'])
        ->name('reports')
        ->middleware('can:reports.view');
    Route::get('/activity-log', [WorkspaceOverviewController::class, 'activityLog'])
        ->name('activity-log')
        ->middleware('can:activity-log.view');

    // Admin-only routes
    Route::middleware('can:users.view')->group(function () {
        Route::inertia('/users', 'Users/Index')->name('users.index');
        Route::inertia('/users/create', 'Users/Create')->name('users.create');
        Route::get('/users/{user}/details', function (User $user, Request $request) {
            abort_unless($request->user()->can('view', $user), 403);

            return Inertia::render('Users/Details', [
                'user' => UserResource::make($user->load('roles'))->toArray($request),
            ]);
        })->name('users.details')->middleware('can:users.view');

        // Invitation management
        Route::inertia('/users/invite', 'Users/Invite')->name('users.invite');
    });

    // Role & Permission Management (system administrator only)
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('/roles/permissions', [RolePermissionController::class, 'index'])
            ->name('roles.permissions');
        Route::put('/roles/permissions/{role}', [RolePermissionController::class, 'update'])
            ->name('roles.permissions.update');
    });

    // Settings route (accessible to all authenticated users)
    Route::middleware('can:settings.view')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
        Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
        Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
        Route::put('/settings/timezone', [SettingsController::class, 'updateTimezone'])->name('settings.timezone.update');
    });

    // API & Webhook Integrations
    Route::middleware('can:settings.view')->group(function () {
        Route::get('/integrations', [ApiIntegrationController::class, 'index'])->name('integrations.index');
        Route::post('/integrations/tokens', [ApiIntegrationController::class, 'storeToken'])->name('integrations.tokens.store');
        Route::delete('/integrations/tokens/{token}', [ApiIntegrationController::class, 'destroyToken'])->name('integrations.tokens.destroy');
        Route::post('/integrations/webhooks', [ApiIntegrationController::class, 'storeWebhook'])->name('integrations.webhooks.store');
        Route::put('/integrations/webhooks/{webhook}', [ApiIntegrationController::class, 'updateWebhook'])->name('integrations.webhooks.update');
        Route::patch('/integrations/webhooks/{webhook}/toggle', [ApiIntegrationController::class, 'toggleWebhook'])->name('integrations.webhooks.toggle');
        Route::delete('/integrations/webhooks/{webhook}', [ApiIntegrationController::class, 'destroyWebhook'])->name('integrations.webhooks.destroy');
        Route::post('/integrations/webhooks/{webhook}/test', [ApiIntegrationController::class, 'testWebhook'])->name('integrations.webhooks.test');
    });

    // Support & Help Center (accessible to all authenticated users)
    Route::inertia('/support', 'Support/Index')->name('support');
});

// Root redirects to dashboard if authenticated, else login
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});
