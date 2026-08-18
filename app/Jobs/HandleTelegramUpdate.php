<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Обработка обновления бота вне HTTP-запроса вебхука: ответ пользователю уходит
 * через api.telegram.org (нередко через прокси и с ретраями), а Telegram считает
 * медленный вебхук упавшим и повторяет доставку того же обновления.
 */
class HandleTelegramUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, mixed>  $update
     */
    public function __construct(public array $update) {}

    public function handle(TelegramUpdateHandler $handler): void
    {
        $handler->handle($this->update);
    }
}
