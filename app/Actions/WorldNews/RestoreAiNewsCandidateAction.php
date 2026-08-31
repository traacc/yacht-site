<?php

declare(strict_types=1);

namespace App\Actions\WorldNews;

use App\Enums\AiNewsCandidateStatus;
use App\Models\AiNewsCandidate;
use DomainException;

/**
 * Возвращает отклонённый материал на модерацию.
 *
 * Отклонение — не приговор: движок мог занизить релевантность или модератор
 * передумал. Обратный переход разрешён только из Rejected и только пока
 * материал не превратился в новость.
 */
final class RestoreAiNewsCandidateAction
{
    public function handle(AiNewsCandidate $candidate): void
    {
        // Условие в самом UPDATE, как и при отклонении: два параллельных
        // нажатия не должны вернуть уже опубликованный материал.
        $updated = AiNewsCandidate::query()
            ->whereKey($candidate->getKey())
            ->where('status', AiNewsCandidateStatus::Rejected->value)
            ->whereNull('news_id')
            ->update(['status' => AiNewsCandidateStatus::Pending->value]);

        if ($updated !== 1) {
            throw new DomainException('Вернуть на модерацию можно только отклонённый материал.');
        }

        $candidate->refresh();
    }
}
