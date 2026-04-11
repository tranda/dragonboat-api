<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{AuthController, AthleteController, RaceController, LayoutController, ConfigController, InitController, UserController};

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/init', [InitController::class, 'index']);

    Route::get('/athletes', [AthleteController::class, 'index']);
    Route::get('/races', [RaceController::class, 'index']);
    Route::get('/layouts/{raceId}', [LayoutController::class, 'show']);
    Route::get('/config', [ConfigController::class, 'show']);

    Route::middleware('role:admin,coach')->group(function () {
        Route::post('/athletes', [AthleteController::class, 'store']);
        Route::put('/athletes/{id}', [AthleteController::class, 'update']);
        Route::delete('/athletes/{id}', [AthleteController::class, 'destroy']);
        Route::post('/athletes/{id}/restore', [AthleteController::class, 'restore']);

        Route::post('/races', [RaceController::class, 'store']);
        Route::post('/races/reorder', [RaceController::class, 'reorder']);
        Route::put('/races/{id}', [RaceController::class, 'update']);
        Route::delete('/races/{id}', [RaceController::class, 'destroy']);
        Route::post('/races/{id}/duplicate', [RaceController::class, 'duplicate']);

        Route::put('/layouts/{raceId}', [LayoutController::class, 'update']);
        Route::put('/config', [ConfigController::class, 'update']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });
});
