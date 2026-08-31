<?php

declare(strict_types=1);

namespace App\Actions\WorldNews;

use App\Models\AiNewsCandidate;
use App\Services\WorldNews\ArticleImageExtractor;

/**
 * Повторно ищет превью-картинку на странице источника.
 *
 * Нужна, когда при поиске картинку достать не удалось (сайт лежал, разметки
 * ещё не было) либо ссылка успела протухнуть.
 */
final class RefreshAiNewsCandidateImageAction
{
    public function __construct(private readonly ArticleImageExtractor $images) {}

    /**
     * @return string|null Найденная ссылка либо null, если извлечь не удалось.
     */
    public function handle(AiNewsCandidate $candidate): ?string
    {
        $imageUrl = $this->images->extract($candidate->source_url);

        // Неудачный повтор не затирает прежнее значение: там может лежать
        // ссылка, которую модератор подставил руками.
        if ($imageUrl === null) {
            return null;
        }

        $candidate->update(['image_url' => $imageUrl]);

        return $imageUrl;
    }
}
