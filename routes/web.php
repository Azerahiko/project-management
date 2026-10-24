<?php

use App\Http\Controllers\DashboardController;
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

// Fortify registers its own POST /login and POST /logout routes automatically.

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('can:dashboard.view');
    Route::inertia('/notifications', 'Notifications/Index')->name('notifications');

    // Project routes
    Route::middleware('can:projects.view')->group(function () {
        Route::inertia('/projects', 'Projects/Index')->name('projects.index');
    });

    Route::middleware('can:projects.create')->group(function () {
        Route::inertia('/projects/create', 'Projects/Create')->name('projects.create');
    });

    Route::middleware('can:projects.view')->group(function () {
        Route::get('/projects/{project}', function (Project $project, Request $request) {
            abort_unless($request->user()->can('view', $project), 403);

            $project->load(['owner', 'projectMembers.user']);
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
            $project->load(['owner', 'projectMembers.user']);
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
        Route::get('/tasks/{task}', function (Task $task, Request $request) {
            abort_unless($request->user()->can('view', $task), 403);

            $task->load(['project', 'assignee', 'creator']);
            $task->can_update = $request->user()->can('update', $task);
            $task->can_delete = $request->user()->can('delete', $task);

            return Inertia::render('Tasks/Show', [
                'task' => TaskResource::make($task)->toArray($request),
            ]);
        })->name('tasks.show');

        Route::get('/tasks/{task}/edit', function (Task $task, Request $request) {
            abort_unless($request->user()->can('update', $task), 403);

            $task->load(['project', 'assignee', 'creator']);
            $task->can_update = $request->user()->can('update', $task);
            $task->can_delete = $request->user()->can('delete', $task);

            return Inertia::render('Tasks/Edit', [
                'task' => TaskResource::make($task)->toArray($request),
            ]);
        })->name('tasks.edit');
    });
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
