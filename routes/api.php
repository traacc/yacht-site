<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\RegattaListController;
use App\Http\Controllers\Api\RegattaParticipantsController;
use App\Http\Controllers\Api\RegattaResultsController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API для внешней программы (судейская программа)
|--------------------------------------------------------------------------
|
| Аутентификация — Bearer-токен (middleware api.token, см. VerifyApiToken).
| Регата резолвится по external_id (Regatta::getRouteKeyName()).
|
|   GET  /api/regattas                         — список регат (поиск external_id)
|   GET  /api/regattas/{regatta}/participants  — экспорт участников (КАРТЕР 30)
|   POST /api/regattas/{regatta}/results       — импорт результатов (КАРТЕР 30)
|
*/

// Вебхуки эквайринга: вне api.token (провайдеры не шлют Bearer),
// аутентификация — подпись запроса внутри адаптера провайдера.
Route::post('payments/webhook/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.payments.webhook');

// Обновления Telegram-бота (привязка чата к аккаунту, команда /stop).
// Аутентификация — секрет из setWebhook в заголовке, см. VerifyTelegramWebhook.
Route::post('telegram/webhook', TelegramWebhookController::class)
    ->middleware(['telegram.webhook', 'throttle:240,1'])
    ->name('api.telegram.webhook');

Route::middleware('api.token')->group(function (): void {
    Route::get('regattas', RegattaListController::class)
        ->name('api.regattas.index');

    Route::get('regattas/{regatta}/participants', RegattaParticipantsController::class)
        ->name('api.regattas.participants');

    Route::post('regattas/{regatta}/results', RegattaResultsController::class)
        ->name('api.regattas.results');
});
