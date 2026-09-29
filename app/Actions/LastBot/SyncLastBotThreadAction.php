<?php

declare(strict_types=1);

namespace App\Actions\LastBot;

use App\Actions\Chat\SendChatMessageAction;
use App\Enums\MessageAuthorRole;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Services\LastBot\LastBotClient;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Забирает ответы из диалога LastBot в наше обращение.
 *
 * Импортируются только ответы (role=assistant): реплики пользователя у нас уже
 * есть — это мы их туда отправили. Ответ ИИ пишется с ролью Bot, ответ живого
 * оператора LastBot (у сообщения есть sender) — с ролью Support. Сообщения идут
 * через SendChatMessageAction, поэтому клиент получает обычные уведомления.
 *
 * Ответ ИИ приходит потоком и дописывается на их стороне, а is_finished у него
 * true с самого создания. Поэтому, как и их виджет, считаем ответ дописанным,
 * когда updated_at не менялся SETTLE_SECONDS (@see isSettled()) — иначе в ленте
 * осталась бы половина фразы. Если текст импортированного ответа всё же
 * изменился, он обновляется молча (без повторных уведомлений).
 * Повторный импорт отсекается по lastbot_message_id (уникален в диалоге).
 */
class SyncLastBotThreadAction
{
    /** Служебные сообщения ассистента, которые их виджет не показывает как ответ. */
    private const SKIPPED_FIELDS = ['tool_call', 'submit_rating'];

    /**
     * Сколько секунд ответ должен не меняться, чтобы считаться дописанным.
     * Виджет LastBot показывает «печатает», пока updated_at моложе 5 секунд;
     * отсчёт идёт от времени их сервера (LastBotClient::SERVER_TIME).
     */
    public const SETTLE_SECONDS = 8;

    public function __construct(
        private readonly LastBotClient $client,
        private readonly SendChatMessageAction $sendMessage,
    ) {}

    /**
     * Опрос диалога. Если диалог прямо сейчас синхронизирует другой процесс,
     * ничего не делаем — он и так заберёт свежие ответы.
     */
    public function handle(Conversation $conversation): LastBotSyncResult
    {
        if (! $this->client->isEnabled() || $conversation->lastbot_thread_id === null || $conversation->lastbot_contact_uuid === null) {
            return new LastBotSyncResult;
        }

        $lock = Cache::lock(self::lockKey($conversation), 60);

        if (! $lock->get()) {
            return new LastBotSyncResult(awaitingReply: true);
        }

        try {
            $thread = $this->client->thread(
                $conversation->lastbot_thread_id,
                $conversation->lastbot_contact_uuid,
                self::sessionId($conversation),
            );

            return $this->import($conversation, $thread);
        } finally {
            $lock->release();
        }
    }

    /**
     * Разбор уже полученного диалога. Вызывающий обязан держать lockKey().
     *
     * @param  array<string, mixed>  $thread
     */
    public function import(Conversation $conversation, array $thread): LastBotSyncResult
    {
        $messages = array_values(array_filter(
            is_array($thread['messages'] ?? null) ? $thread['messages'] : [],
            'is_array',
        ));

        // lastbot_message_id => id нашего сообщения и текст (для обновления).
        $known = $conversation->messages()
            ->whereNotNull('lastbot_message_id')
            ->get(['id', 'lastbot_message_id', 'body'])
            ->keyBy('lastbot_message_id');

        $serverTime = self::serverTime($thread);
        $imported = 0;
        $streaming = false;

        foreach ($messages as $message) {
            if (($message['role'] ?? null) !== 'assistant' || ! isset($message['id'])) {
                continue;
            }

            if (self::isServiceMessage($message)) {
                continue;
            }

            if (! self::isSettled($message, $serverTime)) {
                $streaming = true;

                continue;
            }

            $externalId = (string) $message['id'];
            $body = $this->body($message);

            if ($known->has($externalId)) {
                $this->refreshBody($known->get($externalId), $body);

                continue;
            }

            if ($body === '') {
                continue;
            }

            try {
                $this->sendMessage->handle(
                    conversation: $conversation,
                    author: null,
                    role: empty($message['sender']) ? MessageAuthorRole::Bot : MessageAuthorRole::Support,
                    body: $body,
                    attributes: ['lastbot_message_id' => $externalId],
                );
            } catch (UniqueConstraintViolationException) {
                // Параллельный импорт успел раньше — это не ошибка.
                continue;
            }

            $known->put($externalId, new ChatMessage(['body' => $body]));
            $imported++;
        }

        return new LastBotSyncResult(
            imported: $imported,
            // Ждём ответа, пока ИИ дописывает сообщение или последним в диалоге
            // стоит не ответ. Сразу после создания диалог бывает пуст — LastBot
            // записывает первую реплику не мгновенно, это тоже ожидание.
            awaitingReply: $streaming || ! $this->endsWithReply($messages),
            closed: ! empty($thread['task']['closed_at'] ?? null),
        );
    }

    /**
     * Ответ дописан: LastBot не пометил его незаконченным и не трогал его
     * последние SETTLE_SECONDS по часам LastBot.
     *
     * @param  array<string, mixed>  $message
     */
    public static function isSettled(array $message, CarbonInterface $serverTime): bool
    {
        if (($message['is_finished'] ?? true) === false) {
            return false;
        }

        $updatedAt = $message['updated_at'] ?? null;

        if (! is_string($updatedAt) || $updatedAt === '') {
            return true;
        }

        try {
            return Carbon::parse($updatedAt)->diffInSeconds($serverTime, false) >= self::SETTLE_SECONDS;
        } catch (\Throwable) {
            return true;
        }
    }

    /** @param  array<string, mixed>  $message */
    public static function isServiceMessage(array $message): bool
    {
        $metadata = $message['metadata'] ?? null;

        return is_array($metadata) && in_array($metadata['field_name'] ?? null, self::SKIPPED_FIELDS, true);
    }

    /**
     * Время LastBot на момент ответа (без него — наше).
     *
     * @param  array<string, mixed>  $thread
     */
    public static function serverTime(array $thread): CarbonInterface
    {
        $time = $thread[LastBotClient::SERVER_TIME] ?? null;

        try {
            return is_string($time) ? Carbon::parse($time) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    public static function lockKey(Conversation $conversation): string
    {
        return 'lastbot:conversation:'.$conversation->getKey();
    }

    /** session_id их виджет хранит в sessionStorage; нам хватает id обращения. */
    public static function sessionId(Conversation $conversation): string
    {
        return (string) $conversation->getKey();
    }

    /** Ответ поправили после импорта — обновляем текст без уведомлений. */
    private function refreshBody(ChatMessage $stored, string $body): void
    {
        if ($body === '' || $stored->body === $body || ! $stored->exists) {
            return;
        }

        $stored->forceFill(['body' => $body])->saveQuietly();
    }

    /** @param  array<string, mixed>  $message */
    private function body(array $message): string
    {
        $body = trim((string) ($message['contents'] ?? ''));

        // Файлы LastBot не скачиваем: ссылка на них живёт у LastBot.
        $attachment = $message['attachment'] ?? null;

        if (is_array($attachment) && ! empty($attachment['url'])) {
            $body = trim($body."\n\nВложение: ".($attachment['filename'] ?? 'файл').' — '.$attachment['url']);
        }

        return Str::limit($body, SendChatMessageAction::MAX_LENGTH - 3, '...');
    }

    /** @param  list<array<string, mixed>>  $messages */
    private function endsWithReply(array $messages): bool
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $role = $messages[$i]['role'] ?? null;

            if ($role === 'user') {
                return false;
            }

            // «Поиск информации» (tool_call) приходит раньше ответа и ответом не считается.
            if ($role === 'assistant' && ! self::isServiceMessage($messages[$i])) {
                return true;
            }
        }

        return false;
    }
}
