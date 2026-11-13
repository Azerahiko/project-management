<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectFileController;
use App\Http\Controllers\Api\TaskActivityController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

// Guest activation endpoint (rate limited)
Route::post('/invite/{token}/activate', [InvitationController::class, 'activate'])
    ->middleware(['guest', 'throttle:activation'])
    ->name('api.invitations.activate');

Route::middleware(['auth:sanctum'])->group(function () {
    // Mutation endpoints are rate limited
    Route::middleware('throttle:api.mutation')->group(function () {
        // Invitation management (admin only)
        Route::post('invitations', [InvitationController::class, 'store'])
            ->middleware('can:users.create', 'token.ability:users:write')
            ->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])
            ->middleware('can:users.create', 'token.ability:users:write')
            ->name('invitations.resend');
        Route::post('users', [UserController::class, 'store'])->middleware('can:users.create', 'token.ability:users:write');
        Route::post('users/generate-temporary-password', [UserController::class, 'generateTemporaryPassword'])->middleware('can:users.create', 'token.ability:users:write');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:users.update', 'token.ability:users:write');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete', 'token.ability:users:write');

        // Project routes
        Route::post('projects', [ProjectController::class, 'store'])->middleware('can:projects.create', 'token.ability:projects:write');
        Route::put('projects/{project}', [ProjectController::class, 'update'])->middleware('can:projects.update', 'token.ability:projects:write');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('can:projects.delete', 'token.ability:projects:write');

        // Project member routes
        Route::post('projects/{project}/members', [ProjectController::class, 'addMember'])->middleware('can:projects.manage_members', 'token.ability:projects:write');
        Route::put('projects/{project}/members/{user}', [ProjectController::class, 'updateMember'])->middleware('can:projects.manage_members', 'token.ability:projects:write');
        Route::delete('projects/{project}/members/{user}', [ProjectController::class, 'removeMember'])->middleware('can:projects.manage_members', 'token.ability:projects:write');

        // Task routes
        Route::post('tasks', [TaskController::class, 'store'])->middleware('can:tasks.create', 'token.ability:tasks:write');
        Route::put('tasks/{task}', [TaskController::class, 'update'])->middleware('can:tasks.update', 'token.ability:tasks:write');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->middleware('can:tasks.delete', 'token.ability:tasks:write');

        // Task comments
        Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->middleware('can:task-comments.create', 'token.ability:tasks:write');
        Route::put('comments/{comment}', [TaskCommentController::class, 'update'])->middleware('can:task-comments.update', 'token.ability:tasks:write');
        Route::delete('comments/{comment}', [TaskCommentController::class, 'destroy'])->middleware('can:task-comments.delete', 'token.ability:tasks:write');

        // Project files
        Route::post('projects/{project}/files', [ProjectFileController::class, 'store'])
            ->middleware(['can:projects.update', 'token.ability:projects:write']);
        Route::delete('projects/{project}/files/{file}', [ProjectFileController::class, 'destroy'])
            ->scopeBindings()
            ->middleware(['can:projects.update', 'token.ability:projects:write']);
    });

    // Read-only endpoints (no rate limit)
    Route::get('users', [UserController::class, 'index'])->middleware('can:users.view', 'token.ability:users:read');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:users.view', 'token.ability:users:read');
    Route::get('roles', [UserController::class, 'roles'])->middleware('can:users.view', 'token.ability:users:read');

    // Project routes (read-only)
    Route::get('projects', [ProjectController::class, 'index'])->middleware('can:projects.view', 'token.ability:projects:read');
    Route::get('projects/managers', [ProjectController::class, 'managers'])->middleware('can:projects.create', 'token.ability:projects:read');
    Route::get('projects/trashed', [ProjectController::class, 'trashed'])->middleware('can:projects.view', 'token.ability:projects:read');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('can:projects.view', 'token.ability:projects:read');

    // Project member routes (read-only)
    Route::get('projects/{project}/members', [ProjectController::class, 'members'])->middleware('can:projects.view', 'token.ability:projects:read');
    Route::get('projects/{project}/available-users', [ProjectController::class, 'availableUsers'])->middleware('can:projects.manage_members', 'token.ability:projects:read');

    // Task routes (read-only)
    Route::get('tasks', [TaskController::class, 'index'])->middleware('can:tasks.view', 'token.ability:tasks:read');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->middleware('can:tasks.view', 'token.ability:tasks:read');

    // Task assignees
    Route::get('projects/{project}/assignees', [TaskController::class, 'assignees'])->middleware('can:projects.view', 'token.ability:projects:read');

    // Project files (read-only)
    Route::get('projects/{project}/files', [ProjectFileController::class, 'index'])
        ->middleware(['can:projects.view', 'token.ability:projects:read']);
    Route::get('projects/{project}/files/{file}/download', [ProjectFileController::class, 'download'])
        ->scopeBindings()
        ->middleware(['can:projects.view', 'token.ability:projects:read']);

    // Task comments (read-only)
    Route::middleware(['can:tasks.view', 'can:task-collaboration.view', 'token.ability:tasks:read'])->group(function () {
        Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index']);

        // Task activities
        Route::get('tasks/{task}/activities', [TaskActivityController::class, 'index']);
    });

    // Soft delete restore routes (trash)
    Route::middleware(['auth:sanctum'])->group(function () {
        // Project trash
        Route::post('projects/{project}/restore', [ProjectController::class, 'restore'])->withTrashed()->middleware('can:projects.update', 'token.ability:projects:write');
        // Task trash
        Route::get('projects/{project}/tasks/trashed', [TaskController::class, 'trashed'])->middleware('can:tasks.view', 'token.ability:tasks:read');
        Route::post('tasks/{task}/restore', [TaskController::class, 'restore'])->withTrashed()->middleware('can:tasks.update', 'token.ability:tasks:write');
        // Task comment trash
        Route::get('tasks/{task}/comments/trashed', [TaskCommentController::class, 'trashed'])->middleware('can:tasks.view', 'token.ability:tasks:read');
        Route::post('comments/{comment}/restore', [TaskCommentController::class, 'restore'])->withTrashed()->middleware('can:task-comments.update', 'token.ability:tasks:write');
        // Project file trash
        Route::post('projects/{project}/files/{file}/restore', [ProjectFileController::class, 'restore'])
            ->withTrashed()
            ->scopeBindings()
            ->middleware(['can:projects.update', 'token.ability:projects:write']);
    });

    // Notification routes
    Route::middleware(['can:notifications.view', 'token.ability:notifications:read'])->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });
});
