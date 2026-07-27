<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Приём обновлений Telegram. Подлинность проверяет middleware
 * App\Http\Middleware\VerifyTelegramWebhook.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramUpdateHandler $handler): Response
    {
        try {
            $handler->handle($request->all());
        } catch (Throwable $e) {
            // Никогда не отдаём 5xx: Telegram будет повторять обновление бесконечно.
            report($e);
        }

        return response()->noContent();
    }
}
