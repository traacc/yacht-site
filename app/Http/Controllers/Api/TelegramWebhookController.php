<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\HandleTelegramUpdate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Приём обновлений Telegram. Подлинность проверяет middleware
 * App\Http\Middleware\VerifyTelegramWebhook.
 *
 * Разбор обновления уходит в очередь: Telegram ждёт быстрый ответ и повторяет
 * доставку, если вебхук отвечал долго.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        try {
            HandleTelegramUpdate::dispatch($request->all());
        } catch (Throwable $e) {
            // Никогда не отдаём 5xx: Telegram будет повторять обновление бесконечно.
            report($e);
        }

        return response()->noContent();
    }
}
