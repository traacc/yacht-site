<?php

declare(strict_types=1);

namespace App\Actions\WorldNews;

use App\Actions\News\ReplaceNewsCoverAction;
use App\Models\AiNewsCandidate;
use App\Services\WorldNews\CoverImageDownloader;
use DomainException;

/**
 * Заново берёт обложку опубликованной AI-новости со страницы источника.
 *
 * Нужна, когда при публикации картинка не скачалась или оказалась неудачной,
 * а на странице источника её с тех пор поправили.
 */
final class RedownloadAiNewsCoverAction
{
    public function __construct(
        private readonly RefreshAiNewsCandidateImageAction $refreshImage,
        private readonly CoverImageDownloader $covers,
        private readonly ReplaceNewsCoverAction $replaceCover,
    ) {}

    /**
     * @return bool false — картинку найти или скачать не удалось, обложка не тронута.
     */
    public function handle(AiNewsCandidate $candidate): bool
    {
        $news = $candidate->news;

        if ($news === null) {
            throw new DomainException('У кандидата нет опубликованной новости.');
        }

        // При неудачном поиске остаётся прежняя ссылка — её и пробуем скачать.
        $this->refreshImage->handle($candidate);

        $coverPath = $this->covers->store($candidate->image_url, $candidate->source_url);

        if ($coverPath === null) {
            return false;
        }

        $this->replaceCover->handle($news, $coverPath);

        return true;
    }
}
