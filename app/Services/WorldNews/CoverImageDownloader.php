<?php

declare(strict_types=1);

namespace App\Services\WorldNews;

use App\Services\ImageConverter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Скачивает превью-картинку новости на публичный диск.
 *
 * News::$cover_image_url хранит путь на диске public (его же ждут VkService,
 * TelegramService и уведомления), поэтому внешнюю ссылку недостаточно записать —
 * файл нужно положить рядом с обложками, загруженными руками.
 */
final class CoverImageDownloader
{
    public function __construct(
        private readonly PublicUrlFetcher $fetcher,
        private readonly ImageConverter $images,
    ) {}

    private const DIRECTORY = 'news/covers';

    private const MAX_BYTES = 10485760;

    /**
     * Content-Type ничего не говорит о содержимом: по нему одинаково проходят
     * фотография, трекинг-пиксель 1x1, логотип издания и битый файл.
     *
     * Порог парный, потому что одной стороны мало. Минимальная сторона
     * отсеивает иконки и растяжки вроде 728x90, минимальная площадь — мелкие
     * квадратные логотипы. При этом проходят уменьшенные превью движков
     * (Drupal отдаёт в og:image derivative 220x147) — это настоящее фото
     * материала, пусть и небольшое.
     */
    private const MIN_IMAGE_SIDE = 120;

    private const MIN_IMAGE_PIXELS = 30000;

    /** HEIC/HEIF допускаем: NormalizesHeicImageColumns перекодирует его при сохранении News. */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
        'image/heic' => 'heic',
        'image/heif' => 'heic',
    ];

    /**
     * @param  string|null  $refererUrl  Страница, с которой взята картинка: без
     *                                   заголовка Referer часть CDN отдаёт 403 на прямой запрос (hotlink-защита).
     * @return string|null Путь на диске public либо null, если скачать не удалось.
     */
    public function store(?string $imageUrl, ?string $refererUrl = null): ?string
    {
        if ($imageUrl === null || trim($imageUrl) === '') {
            return null;
        }

        $headers = ['User-Agent' => 'Mozilla/5.0 (compatible; YachtAssociationBot/1.0)'];

        if ($refererUrl !== null && trim($refererUrl) !== '') {
            $headers['Referer'] = trim($refererUrl);
        }

        $response = $this->fetcher->get($imageUrl, headers: $headers, timeout: 30);

        if ($response === null || $response->failed()) {
            return null;
        }

        $extension = self::EXTENSIONS[$this->contentType($response->header('Content-Type'))] ?? null;
        $body = $response->body();

        if ($extension === null || $body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $decoded = $this->decode($body, $extension);

        if ($decoded === null) {
            return null;
        }

        [$body, $extension] = $decoded;

        $path = self::DIRECTORY.'/'.Str::uuid()->toString().'.'.$extension;

        return Storage::disk('public')->put($path, $body) ? $path : null;
    }

    /**
     * Проверяет, что скачанное действительно декодируется в картинку годного
     * размера. HEIC браузеры не показывают и getimagesize его не понимает,
     * поэтому такой файл сразу перегоняем в JPEG — заодно это и есть проверка.
     *
     * @return array{0: string, 1: string}|null Тело и расширение для сохранения.
     */
    private function decode(string $body, string $extension): ?array
    {
        $size = @getimagesizefromstring($body);

        if ($size === false && $extension === 'heic') {
            $jpeg = $this->images->heicBytesToJpeg($body);

            if ($jpeg === null) {
                return null;
            }

            $body = $jpeg;
            $extension = 'jpg';
            $size = @getimagesizefromstring($body);
        }

        if ($size === false) {
            return null;
        }

        [$width, $height] = $size;

        if ($width < self::MIN_IMAGE_SIDE
            || $height < self::MIN_IMAGE_SIDE
            || $width * $height < self::MIN_IMAGE_PIXELS) {
            return null;
        }

        return [$body, $extension];
    }

    private function contentType(?string $header): string
    {
        return strtolower(trim(Str::before((string) $header, ';')));
    }
}
