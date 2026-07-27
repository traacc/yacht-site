<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramDeleteWebhook extends Command
{
    protected $signature = 'telegram:delete-webhook';

    protected $description = 'Удаляет вебхук Telegram (нужно, чтобы работал telegram:poll).';

    public function handle(TelegramService $telegram): int
    {
        $result = $telegram->deleteWebhook();

        if (! $result->ok) {
            $this->error("Не удалось удалить вебхук: {$result->description}");

            return self::FAILURE;
        }

        $this->info('Вебхук удалён.');

        return self::SUCCESS;
    }
}
