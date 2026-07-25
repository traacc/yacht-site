<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Payment\HandleWebhookAction;
use App\Enums\PaymentProviderCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Вебхуки провайдеров эквайринга. Без Bearer-токена: аутентификация —
 * верификация подписи запроса внутри адаптера провайдера (parseWebhook).
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly HandleWebhookAction $handleWebhook,
    ) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $code = PaymentProviderCode::tryFrom($provider);

        if ($code === null) {
            abort(404);
        }

        $status = $this->handleWebhook->handle($code, $request);

        return response()->json(['ok' => $status === 200], $status);
    }
}
