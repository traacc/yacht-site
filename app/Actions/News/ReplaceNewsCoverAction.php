<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Models\News;

/**
 * Меняет обложку уже созданной новости.
 *
 * Прежний файл удаляет NewsObserver::updated — так же, как при правке
 * обложки через форму новости.
 */
final class ReplaceNewsCoverAction
{
    /**
     * @param  string|null  $coverPath  Путь на диске public; null — убрать обложку.
     */
    public function handle(News $news, ?string $coverPath): void
    {
        $news->update(['cover_image_url' => $coverPath]);
    }
}
