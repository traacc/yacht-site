<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--url= : Адрес вебхука, по умолчанию — маршрут api.telegram.webhook} {--drop-pending : Отбросить накопившиеся обновления}';

    protected $description = 'Регистрирует в Telegram вебхук для приёма команд бота (привязка уведомлений).';

    public function handle(TelegramService $telegram): int
    {
        if (! $telegram->hasToken()) {
            $this->error('Не задан TELEGRAM_BOT_TOKEN.');

            return self::FAILURE;
        }

        $secret = (string) config('services.telegram.webhook_secret');

        if ($secret === '') {
            $this->error('Не задан TELEGRAM_WEBHOOK_SECRET — без него вебхук принимать нельзя.');

            return self::FAILURE;
        }

        $url = (string) ($this->option('url') ?: route('api.telegram.webhook'));

        if (! str_starts_with($url, 'https://')) {
            $this->error("Telegram принимает вебхук только по HTTPS, получено: {$url}");

            return self::FAILURE;
        }

        $result = $telegram->setWebhook($url, $secret, (bool) $this->option('drop-pending'));

        if (! $result->ok) {
            $this->error("Не удалось установить вебхук: {$result->description}");

            return self::FAILURE;
        }

        $this->info("Вебхук установлен: {$url}");

        return self::SUCCESS;
    }
}
