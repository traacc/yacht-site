<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Регистрирует конверсии современных форматов (WebP + AVIF) для медиа-коллекций.
 *
 * Обе конверсии — полноразмерные (без ресайза), меняется только формат кодирования.
 * Оригинал (jpg/png/webp) служит `<img>`-фолбэком в `<picture>`; браузер сам выбирает
 * наилучший поддерживаемый источник (avif → webp → оригинал).
 *
 * ★ HEIC: heic/heif-оригинал браузеры не показывают, поэтому для него `<img>`-фолбэком
 * служит НЕ оригинал, а webp-конверсия (webp поддерживают все браузеры с ~2020 г.) —
 * это разруливает App\Support\ResponsiveMedia. Декодирование heic требует драйвера
 * Imagick + libheif с плагином libde265 (см. config/media-library.php, Dockerfile).
 *
 * Конверсии выполняются в очереди (`->queued()`): AVIF-кодирование ресурсоёмко,
 * поэтому сохранение формы им не блокируется. Требуется работающий queue worker
 * (в проекте — `yacht-site-laravel.worker-1`, QUEUE_CONNECTION=redis). До обработки
 * очереди `<picture>` показывает оригинал (см. App\Support\ResponsiveMedia).
 *
 * URL-ы конверсий: $media->getUrl('webp') / $media->getUrl('avif').
 *
 * Использование: вызвать $this->addResponsiveFormatConversions() внутри
 * registerMediaConversions() модели.
 */
trait RegistersResponsiveFormats
{
    public function addResponsiveFormatConversions(int $webpQuality = 80, int $avifQuality = 55): void
    {
        $this->addMediaConversion('webp')
            ->format('webp')
            ->quality($webpQuality)
            ->queued();

        $this->addMediaConversion('avif')
            ->format('avif')
            ->quality($avifQuality)
            ->queued();
    }
}
