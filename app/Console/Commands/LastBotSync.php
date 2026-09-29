<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncLastBotThread;
use App\Models\Conversation;
use App\Services\LastBot\LastBotClient;
use Illuminate\Console\Command;

/**
 * Подбирает поздние ответы из LastBot — прежде всего живых операторов, которые
 * отвечают в своём портале через минуты и часы. Быстрый ответ ИИ ловит цепочка
 * опросов после пересылки (@see SyncLastBotThread).
 */
class LastBotSync extends Command
{
    protected $signature = 'lastbot:sync';

    protected $description = 'Забирает новые ответы из диалогов LastBot по недавно активным обращениям в поддержку.';

    public function handle(LastBotClient $client): int
    {
        if (! $client->isEnabled()) {
            return self::SUCCESS;
        }

        $since = now()->subHours(max(1, (int) config('services.lastbot.sync_window_hours', 24)));

        $count = 0;

        Conversation::query()
            ->support()
            ->whereNotNull('lastbot_thread_id')
            ->where('last_message_at', '>=', $since)
            ->select('id')
            ->each(function (Conversation $conversation) use (&$count): void {
                SyncLastBotThread::dispatch((string) $conversation->getKey(), step: null);
                $count++;
            });

        if ($this->output->isVerbose()) {
            $this->line("Поставлено опросов: {$count}.");
        }

        return self::SUCCESS;
    }
}
