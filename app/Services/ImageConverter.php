<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Конвертация загруженных изображений в WebP.
 *
 * Работает с файлами, уже сохранёнными на диске (относительный путь).
 * Требует расширение GD с поддержкой WebP (в Sail-контейнере — php8.5-gd).
 */
class ImageConverter
{
    /**
     * Изображения, которые имеет смысл перекодировать в WebP.
     *
     * @var array<string>
     */
    private const CONVERTIBLE = ['jpg', 'jpeg', 'png'];

    /**
     * Преобразует изображение по указанному пути в WebP.
     *
     * - Видео и прочие не-изображения возвращаются без изменений.
     * - Файлы, уже являющиеся WebP, возвращаются без изменений.
     * - При недоступности GD/WebP исходный путь сохраняется (graceful fallback).
     *
     * Возвращает путь к итоговому файлу (новый .webp либо исходный).
     */
    public function toWebp(?string $path, string $disk = 'public', int $quality = 82): ?string
    {
        if ($path === null || $path === '') {
            return $path;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, self::CONVERTIBLE, true)) {
            // webp / mp4 / webm и т.п. — не трогаем
            return $path;
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return $path;
        }

        if (! function_exists('imagewebp')) {
            Log::warning('ImageConverter: расширение GD с поддержкой WebP недоступно, конвертация пропущена.', [
                'path' => $path,
            ]);

            return $path;
        }

        $contents = $storage->get($path);
        $image    = @imagecreatefromstring($contents);

        if ($image === false) {
            Log::warning('ImageConverter: не удалось прочитать изображение.', ['path' => $path]);

            return $path;
        }

        // Сохраняем прозрачность для PNG
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagewebp($image, null, $quality);
        $webpContents = ob_get_clean();
        imagedestroy($image);

        if (! $ok || $webpContents === false || $webpContents === '') {
            Log::warning('ImageConverter: кодирование в WebP не удалось.', ['path' => $path]);

            return $path;
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $path);

        // На случай коллизии имён с уже существующим .webp
        if ($webpPath === $path) {
            return $path;
        }

        $storage->put($webpPath, $webpContents, 'public');
        $storage->delete($path);

        return $webpPath;
    }
}
