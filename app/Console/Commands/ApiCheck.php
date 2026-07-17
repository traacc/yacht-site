<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Models\Regatta;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Smoke-тест внешнего API: выпускает временный токен, дёргает все эндпоинты и
 * проверяет коды ответов и форму JSON. По умолчанию НЕ мутирует данные — POST
 * проверяется только на валидацию (422). Временный токен удаляется в конце.
 *
 * Запросы идут реальным HTTP на --base-url (по умолчанию config('app.url')),
 * поэтому команду удобно запускать в контейнере, который отдаёт HTTP:
 *   docker exec yacht-site-laravel.test-1 php artisan api:check
 */
class ApiCheck extends Command
{
    protected $signature = 'api:check
                            {--base-url= : Базовый URL приложения (по умолчанию config app.url)}
                            {--regatta= : external_id регаты для проверок чтения (по умолчанию — авто)}
                            {--keep-token : Не удалять временный токен после проверки}';

    protected $description = 'Проверка внешнего API: коды ответов и форма JSON всех эндпоинтов.';

    /** @var array<int, array{name: string, ok: bool, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');
        $apiBase = "{$baseUrl}/api";

        $this->info("Проверка API: {$apiBase}");

        [$client, $token] = ApiClient::issue('api:check (temp)');

        try {
            $regattaId = $this->resolveRegattaId();

            $this->checkUnauthorized($apiBase);
            $this->checkBadToken($apiBase, 'wrong-token-value');
            $this->checkRegattasList($apiBase, $token);

            if ($regattaId !== null) {
                $this->checkParticipants($apiBase, $token, $regattaId);
                $this->checkResultsValidation($apiBase, $token, $regattaId);
            } else {
                $this->warn('Регат в базе нет — проверки участников и результатов пропущены.');
            }

            $this->checkNotFound($apiBase, $token);
        } catch (Throwable $e) {
            $this->error('Не удалось выполнить проверку: '.$e->getMessage());
            $this->comment('Проверьте, что --base-url указывает на работающий сервер (см. описание команды).');

            return $this->finish($client, self::FAILURE);
        }

        return $this->finish($client, $this->render());
    }

    // ──────────────────────────────────────────────
    // Проверки
    // ──────────────────────────────────────────────

    private function checkUnauthorized(string $apiBase): void
    {
        $resp = $this->http()->get("{$apiBase}/regattas");

        $this->record('GET /regattas без токена → 401', $resp->status() === 401, "код {$resp->status()}");
    }

    private function checkBadToken(string $apiBase, string $badToken): void
    {
        $resp = $this->http()->withToken($badToken)->get("{$apiBase}/regattas");

        $this->record('GET /regattas с неверным токеном → 401', $resp->status() === 401, "код {$resp->status()}");
    }

    private function checkRegattasList(string $apiBase, string $token): void
    {
        $resp = $this->http()->withToken($token)->get("{$apiBase}/regattas");
        $ok = $resp->status() === 200 && is_array($resp->json('data'));

        $count = is_array($resp->json('data')) ? count($resp->json('data')) : 0;
        $this->record('GET /regattas → 200 + data[]', $ok, "код {$resp->status()}, регат: {$count}");
    }

    private function checkParticipants(string $apiBase, string $token, int $regattaId): void
    {
        $resp = $this->http()->withToken($token)->get("{$apiBase}/regattas/{$regattaId}/participants");
        $json = $resp->json();

        $ok = $resp->status() === 200
            && isset($json['regatta']['external_id'])
            && array_key_exists('class', $json)
            && is_array($json['participants'] ?? null);

        $count = is_array($json['participants'] ?? null) ? count($json['participants']) : 0;
        $this->record("GET /regattas/{$regattaId}/participants → 200 + форма", $ok, "код {$resp->status()}, участников: {$count}");
    }

    private function checkResultsValidation(string $apiBase, string $token, int $regattaId): void
    {
        // Пустое тело — ожидаем 422 (crews обязателен). Данные НЕ изменяются.
        $resp = $this->http()->withToken($token)
            ->post("{$apiBase}/regattas/{$regattaId}/results", ['races' => [], 'crews' => []]);
        $json = $resp->json();

        $ok = $resp->status() === 422
            && isset($json['message'])
            && isset($json['errors']['crews']);

        $this->record("POST /regattas/{$regattaId}/results (пусто) → 422", $ok, "код {$resp->status()}");
    }

    private function checkNotFound(string $apiBase, string $token): void
    {
        $resp = $this->http()->withToken($token)->get("{$apiBase}/regattas/999999999/participants");

        $this->record('GET /regattas/{несуществующая}/participants → 404', $resp->status() === 404, "код {$resp->status()}");
    }

    // ──────────────────────────────────────────────
    // Инфраструктура
    // ──────────────────────────────────────────────

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(15);
    }

    private function resolveRegattaId(): ?int
    {
        if (filled($this->option('regatta'))) {
            return (int) $this->option('regatta');
        }

        // Предпочитаем регату с заявками — так проверка участников содержательнее.
        $regatta = Regatta::query()
            ->withCount('entries')
            ->orderByDesc('entries_count')
            ->first();

        return $regatta?->external_id;
    }

    private function record(string $name, bool $ok, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
        $this->line(($ok ? '<info>  ✓</info>' : '<error>  ✗</error>')." {$name}".($detail !== '' ? " <fg=gray>({$detail})</>" : ''));
    }

    private function render(): int
    {
        $passed = count(array_filter($this->results, fn ($r) => $r['ok']));
        $total = count($this->results);

        $this->newLine();

        if ($passed === $total) {
            $this->info("Все проверки пройдены: {$passed}/{$total}.");

            return self::SUCCESS;
        }

        $this->error("Пройдено {$passed}/{$total}. Провалены:");
        foreach ($this->results as $r) {
            if (! $r['ok']) {
                $this->line("  • {$r['name']} — {$r['detail']}");
            }
        }

        return self::FAILURE;
    }

    private function finish(ApiClient $client, int $code): int
    {
        if ($this->option('keep-token')) {
            $this->comment("Временный токен сохранён (id={$client->id}).");
        } else {
            $client->delete();
        }

        return $code;
    }
}
