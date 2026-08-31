<?php

declare(strict_types=1);

namespace App\Services\WorldNews;

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
    public function __construct(private readonly PublicUrlFetcher $fetcher) {}

    private const DIRECTORY = 'news/covers';

    private const MAX_BYTES = 10485760;

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
     * @return string|null Путь на диске public либо null, если скачать не удалось.
     */
    public function store(?string $imageUrl): ?string
    {
        if ($imageUrl === null || trim($imageUrl) === '') {
            return null;
        }

        $response = $this->fetcher->get(
            $imageUrl,
            headers: ['User-Agent' => 'Mozilla/5.0 (compatible; YachtAssociationBot/1.0)'],
            timeout: 30,
        );

        if ($response === null || $response->failed()) {
            return null;
        }

        $extension = self::EXTENSIONS[$this->contentType($response->header('Content-Type'))] ?? null;
        $body = $response->body();

        if ($extension === null || $body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $path = self::DIRECTORY.'/'.Str::uuid()->toString().'.'.$extension;

        return Storage::disk('public')->put($path, $body) ? $path : null;
    }

    private function contentType(?string $header): string
    {
        return strtolower(trim(Str::before((string) $header, ';')));
    }
}
