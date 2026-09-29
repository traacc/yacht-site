<?php

declare(strict_types=1);

namespace App\Actions\LastBot;

/** Итог опроса диалога LastBot. */
final readonly class LastBotSyncResult
{
    public function __construct(
        /** Сколько ответов добавлено в обращение. */
        public int $imported = 0,
        /** Ответ ещё пишется или не начат — диалог стоит опросить снова. */
        public bool $awaitingReply = false,
        /** Диалог закрыт на стороне LastBot: следующая реплика откроет новый. */
        public bool $closed = false,
    ) {}
}
