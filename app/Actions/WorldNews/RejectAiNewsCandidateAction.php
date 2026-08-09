<?php

declare(strict_types=1);

namespace App\Actions\WorldNews;

use App\Enums\AiNewsCandidateStatus;
use App\Models\AiNewsCandidate;
use DomainException;

final class RejectAiNewsCandidateAction
{
    public function handle(AiNewsCandidate $candidate): void
    {
        $updated = AiNewsCandidate::query()
            ->whereKey($candidate->getKey())
            ->where('status', AiNewsCandidateStatus::Pending->value)
            ->whereNull('news_id')
            ->update(['status' => AiNewsCandidateStatus::Rejected->value]);

        if ($updated !== 1) {
            throw new DomainException('Отклонить можно только материал на модерации.');
        }

        $candidate->refresh();
    }
}
