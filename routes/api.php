<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
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
            ->middleware('can:users.create')
            ->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])
            ->middleware('can:users.create')
            ->name('invitations.resend');
        Route::post('users', [UserController::class, 'store'])->middleware('can:users.create');
        Route::post('users/generate-temporary-password', [UserController::class, 'generateTemporaryPassword'])->middleware('can:users.create');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete');

        // Project routes
        Route::post('projects', [ProjectController::class, 'store'])->middleware('can:projects.create');
        Route::put('projects/{project}', [ProjectController::class, 'update'])->middleware('can:projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('can:projects.delete');

        // Project member routes
        Route::post('projects/{project}/members', [ProjectController::class, 'addMember'])->middleware('can:projects.manage_members');
        Route::put('projects/{project}/members/{user}', [ProjectController::class, 'updateMember'])->middleware('can:projects.manage_members');
        Route::delete('projects/{project}/members/{user}', [ProjectController::class, 'removeMember'])->middleware('can:projects.manage_members');

        // Task routes
        Route::post('tasks', [TaskController::class, 'store'])->middleware('can:tasks.view');
        Route::put('tasks/{task}', [TaskController::class, 'update'])->middleware('can:tasks.view');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->middleware('can:tasks.view');

        // Task comments
        Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store']);
        Route::put('comments/{comment}', [TaskCommentController::class, 'update']);
        Route::delete('comments/{comment}', [TaskCommentController::class, 'destroy']);
    });

    // Read-only endpoints (no rate limit)
    Route::get('users', [UserController::class, 'index'])->middleware('can:users.view');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:users.view');
    Route::get('roles', [UserController::class, 'roles'])->middleware('can:users.view');

    // Project routes (read-only)
    Route::get('projects', [ProjectController::class, 'index'])->middleware('can:projects.view');
    Route::get('projects/managers', [ProjectController::class, 'managers'])->middleware('can:projects.create');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('can:projects.view');

    // Project member routes (read-only)
    Route::get('projects/{project}/members', [ProjectController::class, 'members'])->middleware('can:projects.view');
    Route::get('projects/{project}/available-users', [ProjectController::class, 'availableUsers'])->middleware('can:projects.manage_members');

    // Task routes (read-only)
    Route::get('tasks', [TaskController::class, 'index'])->middleware('can:tasks.view');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->middleware('can:tasks.view');

    // Task assignees
    Route::get('projects/{project}/assignees', [TaskController::class, 'assignees'])->middleware('can:projects.view');

    // Task comments (read-only)
    Route::middleware(['can:tasks.view', 'can:task-collaboration.view'])->group(function () {
        Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index']);

        // Task activities
        Route::get('tasks/{task}/activities', [TaskActivityController::class, 'index']);
    });

    // Notification routes
    Route::middleware('can:notifications.view')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });
});
