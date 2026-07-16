<?php

declare(strict_types=1);

use App\Http\Controllers\Api\RegattaParticipantsController;
use App\Http\Controllers\Api\RegattaResultsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API для внешней программы (судейская программа)
|--------------------------------------------------------------------------
|
| Аутентификация — Bearer-токен (middleware api.token, см. VerifyApiToken).
| Регата резолвится по external_id (Regatta::getRouteKeyName()).
|
|   GET  /api/regattas/{regatta}/participants  — экспорт участников (КАРТЕР 30)
|   POST /api/regattas/{regatta}/results       — импорт результатов (КАРТЕР 30)
|
*/

Route::middleware('api.token')->group(function (): void {
    Route::get('regattas/{regatta}/participants', RegattaParticipantsController::class)
        ->name('api.regattas.participants');

    Route::post('regattas/{regatta}/results', RegattaResultsController::class)
        ->name('api.regattas.results');
});
