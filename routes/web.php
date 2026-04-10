<?php

use Illuminate\Support\Facades\Route;

// Serve the React SPA for all non-API, non-asset routes
Route::get('/{any?}', function () {
    return response(file_get_contents(public_path('index.html')), 200)
        ->header('Content-Type', 'text/html');
})->where('any', '^(?!api|assets|favicon\.|icons\.).*$');
