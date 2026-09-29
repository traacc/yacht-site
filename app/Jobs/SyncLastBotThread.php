<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\LastBot\SyncLastBotThreadAction;
use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Опрос диалога LastBot.
 *
 * Уведомлений о новых сообщениях LastBot наружу не шлёт, поэтому после каждой
 * пересылки ответ ИИ ждём частым опросом с нарастающей паузой (DELAYS), пока
 * он не придёт целиком. Поздние ответы живых операторов LastBot подбирает
 * плановая команда lastbot:sync — она ставит эту же задачу без цепочки.
 *
 * Уникальность — чтобы при заторе очереди ежеминутные опросы одного диалога
 * не копились; шаги цепочки различаются номером и друг другу не мешают.
 */
class SyncLastBotThread implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Паузы между опросами, секунды: ~4,5 минуты в сумме. */
    public const DELAYS = [3, 3, 5, 5, 10, 15, 30, 60, 120];

    /** Сбой одного опроса не повторяем: следующий опрос всё равно будет. */
    public int $tries = 1;

    public int $uniqueFor = 300;

    public function __construct(
        public string $conversationId,
        /** Номер шага в DELAYS; null — разовый опрос без продолжения. */
        public ?int $step = 0,
    ) {}

    public function uniqueId(): string
    {
        return $this->conversationId.':'.($this->step ?? 'scheduled');
    }

    public function handle(SyncLastBotThreadAction $sync): void
    {
        $conversation = Conversation::query()->find($this->conversationId);

        if ($conversation === null) {
            return;
        }

        try {
            $result = $sync->handle($conversation);
        } catch (\Throwable $e) {
            Log::warning('LastBot: thread sync failed', [
                'conversation' => $this->conversationId,
                'error' => $e->getMessage(),
            ]);

            $result = null;
        }

        // При сбое цепочку не рвём: LastBot мог просто моргнуть.
        $next = $this->step === null ? null : $this->step + 1;

        if ($next === null || ! isset(self::DELAYS[$next]) || ($result !== null && ! $result->awaitingReply)) {
            return;
        }

        self::dispatch($this->conversationId, $next)->delay(now()->addSeconds(self::DELAYS[$next]));
    }
}
