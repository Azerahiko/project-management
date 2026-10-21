<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->middleware('can:users.view');
    Route::post('users', [UserController::class, 'store'])->middleware('can:users.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:users.view');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete');

    Route::get('roles', [UserController::class, 'roles'])->middleware('can:users.view');
});
