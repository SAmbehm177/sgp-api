<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MembreController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\ProjetController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('projets', ProjetController::class);
    Route::post('projets/{projet}/membres', [MembreController::class, 'store']);
    Route::delete('projets/{projet}/membres/{personnel}', [MembreController::class, 'retirer']);

    Route::middleware('can:admin')->group(function () {
        Route::post('/personnels', [PersonnelController::class, 'store']);
        Route::patch('/users/{user}/status', [UserController::class, 'changerStatus']);
    });
});
