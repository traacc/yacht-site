<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LastBot\SyncLastBotThreadAction;
use App\Services\LastBot\LastBotClient;
use App\Services\LastBot\LastBotException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Проверка связки с LastBot. Протокол недокументированный, поэтому после
 * обновлений их виджета первым делом стоит прогнать эту команду с --send.
 */
class LastBotCheck extends Command
{
    protected $signature = 'lastbot:check
        {--send= : Текст тестового вопроса: создаёт диалог в LastBot и ждёт ответ}
        {--follow= : Вторая реплика в тот же диалог (проверка канала ActionCable)}
        {--wait=60 : Сколько секунд ждать ответ}';

    protected $description = 'Проверяет подключение к LastBot (токен и настройки виджета) и, по желанию, полный цикл вопрос — ответ.';

    public function handle(LastBotClient $client): int
    {
        if (! $client->isEnabled()) {
            $this->error('LastBot выключен или не настроен: нужны LASTBOT_ENABLED=true, LASTBOT_BASE_URL, LASTBOT_WIDGET_ID.');

            return self::FAILURE;
        }

        $this->line('Аккаунт: '.config('services.lastbot.base_url').', виджет: '.config('services.lastbot.widget_id')
            .', Origin: '.config('services.lastbot.origin'));

        try {
            $client->token(fresh: true);
            $this->info('Токен виджета получен.');

            $widget = $client->widget();
            $this->info('Виджет найден'.(isset($widget['allowed_origin']) ? ', разрешённый домен: '.json_encode($widget['allowed_origin'], JSON_UNESCAPED_SLASHES) : '').'.');

            $question = $this->option('send');

            if ($question === null || $question === '') {
                $this->line('--send не указан — диалог не создавался.');

                return self::SUCCESS;
            }

            $session = (string) Str::uuid();
            $thread = $client->createThread(null, (string) $question, $session);
            $threadId = (string) $thread['id'];
            $uuid = (string) $thread['contact']['uuid'];
            $this->info("Диалог создан: {$threadId}, контакт: {$uuid}.");

            if (! $this->awaitReply($client, $threadId, $uuid, $session, 1)) {
                return self::FAILURE;
            }

            $follow = $this->option('follow');

            if ($follow === null || $follow === '') {
                return self::SUCCESS;
            }

            $echo = $client->sendToThread($threadId, $uuid, (string) $follow);
            $echo
                ? $this->info('Вторая реплика отправлена через ActionCable, эхо получено.')
                : $this->warn('Вторая реплика отправлена, но эха не дождались.');

            return $this->awaitReply($client, $threadId, $uuid, $session, 2) ? self::SUCCESS : self::FAILURE;
        } catch (LastBotException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** Ждёт, пока в диалоге наберётся $replies законченных ответов ассистента. */
    private function awaitReply(LastBotClient $client, string $threadId, string $uuid, string $session, int $replies): bool
    {
        $deadline = time() + max(5, (int) $this->option('wait'));

        do {
            sleep(3);

            $thread = $client->thread($threadId, $uuid, $session);
            $serverTime = SyncLastBotThreadAction::serverTime($thread);
            $answers = array_values(array_filter(
                $thread['messages'] ?? [],
                static fn ($m): bool => is_array($m) && ($m['role'] ?? null) === 'assistant'
                    && ! SyncLastBotThreadAction::isServiceMessage($m) && SyncLastBotThreadAction::isSettled($m, $serverTime),
            ));

            if (count($answers) >= $replies) {
                $last = end($answers);
                $this->info(($last['sender'] ?? null) ? 'Ответ оператора:' : 'Ответ ИИ:');
                $this->line((string) ($last['contents'] ?? ''));

                return true;
            }
        } while (time() < $deadline);

        $this->error('Ответ не пришёл за отведённое время.');

        return false;
    }
}
