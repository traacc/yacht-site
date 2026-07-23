<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Собирает набор URL-ов одного медиафайла для отдачи через `<picture>`.
 *
 * Возвращает массив вида ['src' => ..., 'webp' => ..., 'avif' => ...], где:
 *   - 'src'  — всегда присутствует: оригинал (или указанная фолбэк-конверсия);
 *   - 'webp' / 'avif' — присутствуют ТОЛЬКО если соответствующая конверсия уже
 *     сгенерирована (hasGeneratedConversion). Это даёт graceful fallback: пока
 *     очередь не отработала (или для старых медиа до media-library:regenerate)
 *     ключи отсутствуют и `<source>` не выводятся — битых источников не будет.
 *
 * HEIC не хранится как оригинал: при загрузке он нормализуется в JPEG
 * (см. App\Providers\AppServiceProvider — Spatie saveUploadedFileUsing), поэтому
 * оригинал всегда браузеро-совместим и специальной обработки здесь не требуется.
 *
 * Конверсии webp/avif регистрируются трейтом App\Models\Concerns\RegistersResponsiveFormats.
 */
class ResponsiveMedia
{
    /**
     * @return array{src: string, webp?: string, avif?: string}
     */
    public static function urls(Media $media, ?string $fallbackConversion = null): array
    {
        $urls = [
            'src' => $fallbackConversion !== null && $media->hasGeneratedConversion($fallbackConversion)
                ? $media->getUrl($fallbackConversion)
                : $media->getUrl(),
        ];

        if ($media->hasGeneratedConversion('webp')) {
            $urls['webp'] = $media->getUrl('webp');
        }

        if ($media->hasGeneratedConversion('avif')) {
            $urls['avif'] = $media->getUrl('avif');
        }

        return $urls;
    }
}
