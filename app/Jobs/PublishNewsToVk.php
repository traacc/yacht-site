<?php

namespace App\Jobs;

use App\Models\News;
use App\Services\VkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishNewsToVk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  bool  $force  Принудительная отправка (ручная публикация из админки),
     *                       игнорирует флаг published_to_vk и отправляет повторно.
     */
    public function __construct(public string $newsId, public bool $force = false) {}

    public function handle(VkService $vk): void
    {
        if (! $this->force && $this->attempts() === 1) {
            // Атомарно «захватываем» новость: помечаем published_to_vk только если
            // она ещё не была опубликована. Это защищает от двойной отправки, когда
            // джоба попадает в очередь и из observer'а, и из плановой команды.
            // На повторных заходах захват уже наш — перезахватывать не нужно.
            $claimed = News::query()
                ->whereKey($this->newsId)
                ->where('published_to_vk', false)
                ->update(['published_to_vk' => true]);

            if ($claimed === 0) {
                return;
            }
        }

        $news = News::find($this->newsId);

        if ($news === null || ! $news->isPublished()) {
            // Новость удалена или снята с публикации — освобождаем захват.
            if (! $this->force) {
                $news?->updateQuietly(['published_to_vk' => false]);
            }

            return;
        }

        $lastAttempt = $this->attempts() >= $this->tries;

        // Пока попытки есть, требуем, чтобы обложка доехала: сервер загрузки VK
        // отвечает пустым «photo» на разовых сбоях, и повтор обычно проходит.
        // На последней попытке публикуем как есть, чтобы новость не потерялась.
        if ($vk->publishNews($news, requireCover: ! $lastAttempt)) {
            // Помечаем как отправленную (в force-режиме «захват» не ставил флаг).
            if (! $news->published_to_vk) {
                $news->updateQuietly(['published_to_vk' => true]);
            }

            return;
        }

        // Отправка не удалась — вернём джобу в очередь. Захват при этом не
        // снимаем: пока джоба жива, плановая команда не должна ставить дубль.
        if (! $lastAttempt) {
            $this->release($this->backoff);

            return;
        }

        // Попытки исчерпаны. В обычном режиме освобождаем захват, чтобы
        // плановая команда повторила; в force-режиме чужой флаг не трогаем.
        if (! $this->force) {
            $news->updateQuietly(['published_to_vk' => false]);
        }

        $this->fail(new \RuntimeException(
            "Не удалось опубликовать новость {$this->newsId} в VK (попыток: {$this->attempts()})"
        ));
    }

    /**
     * Страховка на случай, когда джоба падает не через handle() (исключение
     * в очереди, превышение $tries воркером): захват нужно снять, иначе новость
     * навсегда останется помеченной как отправленная.
     */
    public function failed(?\Throwable $exception): void
    {
        if ($this->force) {
            return;
        }

        News::query()->whereKey($this->newsId)->update(['published_to_vk' => false]);
    }
}
