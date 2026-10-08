<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('can:admin')->group(function () {
        Route::post('/personnels', [PersonnelController::class, 'store']);
        Route::patch('/users/{user}/statut', [UserController::class, 'changerStatus']);
    });
});
