<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация внешнего API по Bearer-токену.
 *
 * Токен ищется среди действующих api_clients (по sha256-хешу). При совпадении
 * клиент кладётся в атрибут запроса ('api_client') и обновляется last_used_at.
 * Иначе — 401. Подключается алиасом 'api.token' к маршрутам routes/api.php.
 */
class VerifyApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $client = $token ? ApiClient::findByToken($token) : null;

        if ($client === null) {
            return response()->json([
                'message' => 'Неверный или отсутствующий API-токен.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $client->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
