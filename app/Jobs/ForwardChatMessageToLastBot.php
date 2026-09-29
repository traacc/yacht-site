<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\LastBot\ForwardMessageToLastBotAction;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Services\LastBot\LastBotClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Пересылка сообщения клиента в LastBot и запуск ожидания ответа.
 *
 * В очереди, а не в запросе: WebSocket-сессия с LastBot занимает секунды,
 * а их недоступность не должна мешать пользователю писать в чат.
 */
class ForwardChatMessageToLastBot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public string $messageId) {}

    /** Постановка в очередь, если сообщение вообще нужно пересылать. */
    public static function dispatchFor(ChatMessage $message, ?Conversation $conversation = null): void
    {
        if (! app(LastBotClient::class)->isEnabled() || ! ForwardMessageToLastBotAction::applies($message, $conversation)) {
            return;
        }

        self::dispatch((string) $message->getKey())->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(ForwardMessageToLastBotAction $forward): void
    {
        $message = ChatMessage::query()->find($this->messageId);

        if ($message === null) {
            return;
        }

        if ($forward->handle($message)) {
            SyncLastBotThread::dispatch((string) $message->conversation_id)
                ->delay(now()->addSeconds(SyncLastBotThread::DELAYS[0]));
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('LastBot: chat message was not forwarded', [
            'message' => $this->messageId,
            'error' => $e->getMessage(),
        ]);
    }
}
