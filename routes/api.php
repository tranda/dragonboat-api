<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{AuthController, AthleteController, RaceController, LayoutController, ConfigController, InitController, UserController, ImportController, ActivityLogController, CrewSheetController, CompetitionController, TeamController, EventsImportController, ChangesController, PublicCrewController, ReportExportController, ClubImportController, PublicResultsController, PublicListController};

Route::post('/login', [AuthController::class, 'login']);
Route::get('/crew-sheet', [CrewSheetController::class, 'show']);
// Public read-only crew/roster export for EDBF (open for now; see PUBLIC_CREWS_KEY).
Route::get('/public/crews', [PublicCrewController::class, 'index']);
// Public read-only medal results feed for club.motion.rs to pull (see CLUB_RESULTS_KEY).
Route::get('/public/results', [PublicResultsController::class, 'index']);
// Public read-only lists for club.motion.rs to populate team + competition dropdowns.
Route::get('/public/teams', [PublicListController::class, 'teams']);
Route::get('/public/competitions', [PublicListController::class, 'competitions']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/init', [InitController::class, 'index']);
    Route::get('/changes', [ChangesController::class, 'index']);

    Route::get('/athletes', [AthleteController::class, 'index']);
    Route::get('/races', [RaceController::class, 'index']);
    Route::get('/layouts/{raceId}', [LayoutController::class, 'show']);
    Route::get('/config', [ConfigController::class, 'show']);
    Route::post('/pdf-token', [CrewSheetController::class, 'createToken']);

    Route::middleware('role:admin,coach')->group(function () {
        Route::post('/athletes', [AthleteController::class, 'store']);
        Route::put('/athletes/{id}', [AthleteController::class, 'update']);
        Route::delete('/athletes/{id}', [AthleteController::class, 'destroy']);
        Route::post('/athletes/{id}/restore', [AthleteController::class, 'restore']);
        Route::post('/athletes/{id}/register', [AthleteController::class, 'register']);
        Route::post('/athletes/{id}/unregister', [AthleteController::class, 'unregister']);

        Route::post('/races', [RaceController::class, 'store']);
        Route::post('/races/reorder', [RaceController::class, 'reorder']);
        Route::put('/races/{id}', [RaceController::class, 'update']);
        Route::delete('/races/{id}', [RaceController::class, 'destroy']);
        Route::post('/races/{id}/duplicate', [RaceController::class, 'duplicate']);

        // Per-member achievements pivot for one event + club, streamed as CSV.
        Route::get('/report/export', [ReportExportController::class, 'csv']);

        Route::put('/layouts/{raceId}', [LayoutController::class, 'update']);
        Route::post('/layouts/{raceId}/undo', [LayoutController::class, 'undo']);
        Route::post('/layouts/{raceId}/redo', [LayoutController::class, 'redo']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::put('/config', [ConfigController::class, 'update']);
        Route::post('/import', [ImportController::class, 'store']);

        Route::get('/activity-log', [ActivityLogController::class, 'index']);
        Route::post('/events-import/athletes', [EventsImportController::class, 'fetchAthletes']);
        Route::post('/events-import/clubs', [EventsImportController::class, 'fetchClubs']);

        // Sync membership numbers from club.motion.rs onto the active team's athletes.
        Route::post('/club-import/sync', [ClubImportController::class, 'sync']);

        Route::get('/competitions', [CompetitionController::class, 'index']);
        Route::post('/competitions', [CompetitionController::class, 'store']);
        Route::put('/competitions/{id}', [CompetitionController::class, 'update']);
        Route::delete('/competitions/{id}', [CompetitionController::class, 'destroy']);
        Route::post('/competitions/{id}/teams', [CompetitionController::class, 'addTeam']);
        Route::delete('/competitions/{id}/teams/{teamId}', [CompetitionController::class, 'removeTeam']);

        Route::get('/teams', [TeamController::class, 'index']);
        Route::post('/teams', [TeamController::class, 'store']);
        Route::put('/teams/{id}', [TeamController::class, 'update']);
        Route::delete('/teams/{id}', [TeamController::class, 'destroy']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });
});
