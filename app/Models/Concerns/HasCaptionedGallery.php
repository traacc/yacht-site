<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\ResponsiveMedia;
use App\Support\VideoEmbed;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Галерея с подписями и список видео — общее для контентных моделей сайта.
 *
 * Подписи к фотографиям хранятся в колонке `name` модели медиа: это
 * «человеческое» имя файла, отдельное от `file_name`, поэтому своей таблицы
 * подписей заводить не пришлось. Видео лежат в json-колонке `video_links`
 * вида `[{url, caption}]` — тоже без отдельной таблицы.
 *
 * Требует от модели: коллекцию медиа `gallery` и колонку `video_links`.
 */
trait HasCaptionedGallery
{
    /**
     * Фотографии с подписями и адаптивными форматами.
     *
     * @return list<array{src: string, webp: string|null, avif: string|null, caption: string}>
     */
    public function galleryPhotos(string $collection = 'gallery'): array
    {
        return $this->getMedia($collection)
            ->map(function (Media $media): array {
                $urls = ResponsiveMedia::urls($media);

                return [
                    'src' => $urls['src'],
                    'webp' => $urls['webp'] ?? null,
                    'avif' => $urls['avif'] ?? null,
                    // Подпись — имя медиа; если админ его не менял, там лежит
                    // имя исходного файла, поэтому пустую подпись не выдумываем.
                    'caption' => $this->captionFor($media),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Видео с подписями, готовые к вставке в iframe.
     *
     * @return list<array{embed_url: string, url: string, caption: string}>
     */
    public function videos(): array
    {
        return collect($this->video_links ?? [])
            ->filter(fn ($link) => is_array($link) && ! empty($link['url']))
            ->map(fn (array $link): array => [
                'url' => (string) $link['url'],
                'embed_url' => VideoEmbed::url((string) $link['url']),
                'caption' => (string) ($link['caption'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /** Подпись под фото: пустая, если админ оставил исходное имя файла. */
    private function captionFor(Media $media): string
    {
        $name = trim((string) $media->name);
        $originalName = pathinfo((string) $media->file_name, PATHINFO_FILENAME);

        return $name === '' || $name === $originalName ? '' : $name;
    }
}
