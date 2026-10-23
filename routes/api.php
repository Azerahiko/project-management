<?php

use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskActivityController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->middleware('can:users.view');
    Route::post('users', [UserController::class, 'store'])->middleware('can:users.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:users.view');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete');

    Route::get('roles', [UserController::class, 'roles'])->middleware('can:users.view');

    // Project routes
    Route::get('projects', [ProjectController::class, 'index'])->middleware('can:projects.view');
    Route::post('projects', [ProjectController::class, 'store'])->middleware('can:projects.create');
    Route::get('projects/managers', [ProjectController::class, 'managers'])->middleware('can:projects.create');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('can:projects.view');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->middleware('can:projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('can:projects.delete');

    // Project member routes
    Route::get('projects/{project}/members', [ProjectController::class, 'members'])->middleware('can:projects.view');
    Route::post('projects/{project}/members', [ProjectController::class, 'addMember'])->middleware('can:projects.manage_members');
    Route::put('projects/{project}/members/{user}', [ProjectController::class, 'updateMember'])->middleware('can:projects.manage_members');
    Route::delete('projects/{project}/members/{user}', [ProjectController::class, 'removeMember'])->middleware('can:projects.manage_members');

    // Task routes
    Route::get('tasks', [TaskController::class, 'index'])->middleware('can:tasks.view');
    Route::post('tasks', [TaskController::class, 'store'])->middleware('can:tasks.view');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->middleware('can:tasks.view');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->middleware('can:tasks.view');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->middleware('can:tasks.view');

    // Task assignees
    Route::get('projects/{project}/assignees', [TaskController::class, 'assignees'])->middleware('can:projects.view');

    // Task comments
    Route::middleware(['can:tasks.view', 'can:task-collaboration.view'])->group(function () {
        Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index']);
        Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store']);
        Route::put('comments/{comment}', [TaskCommentController::class, 'update']);
        Route::delete('comments/{comment}', [TaskCommentController::class, 'destroy']);

        // Task activities
        Route::get('tasks/{task}/activities', [TaskActivityController::class, 'index']);
    });
});
