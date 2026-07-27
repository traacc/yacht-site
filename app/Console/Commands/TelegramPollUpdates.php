<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramUpdateHandler;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Long polling вместо вебхука — для локальной разработки, куда Telegram
 * достучаться не может. В продакшене используйте telegram:set-webhook.
 */
class TelegramPollUpdates extends Command
{
    protected $signature = 'telegram:poll {--timeout=30 : Длительность длинного опроса, секунд}';

    protected $description = 'Читает обновления бота через getUpdates (замена вебхука для локальной разработки).';

    public function handle(TelegramService $telegram, TelegramUpdateHandler $handler): int
    {
        if (app()->isProduction()) {
            $this->error('В продакшене используйте вебхук: telegram:set-webhook.');

            return self::FAILURE;
        }

        if (! $telegram->hasToken()) {
            $this->error('Не задан TELEGRAM_BOT_TOKEN.');

            return self::FAILURE;
        }

        $webhook = $telegram->getWebhookInfo();

        if ($webhook->ok && filled($webhook->payload['url'] ?? null)) {
            $this->error('Установлен вебхук — getUpdates работать не будет. Сначала выполните telegram:delete-webhook.');

            return self::FAILURE;
        }

        $timeout = (int) $this->option('timeout');
        $offset = null;

        $this->info('Слушаю обновления. Ctrl+C для выхода.');

        while (true) {
            foreach ($telegram->getUpdates($offset, $timeout) as $update) {
                $offset = ((int) ($update['update_id'] ?? 0)) + 1;

                try {
                    $handler->handle($update);
                    $this->line('Обработано обновление '.($update['update_id'] ?? '?'));
                } catch (Throwable $e) {
                    report($e);
                    $this->error('Ошибка обработки: '.$e->getMessage());
                }
            }
        }
    }
}
