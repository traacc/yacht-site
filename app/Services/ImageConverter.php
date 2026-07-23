<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        $image = @imagecreatefromstring($contents);

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

    /**
     * Форматы, которые браузеры не показывают и которые нужно нормализовать.
     *
     * @var array<string>
     */
    private const HEIC_EXTENSIONS = ['heic', 'heif'];

    /**
     * Нормализует HEIC/HEIF в WebP (для строковых полей-путей).
     *
     * В отличие от toWebp() (GD, только jpg/png), использует Imagick — GD не декодирует HEIC.
     * Декодирование HEVC-HEIC требует libheif с плагином libde265 (см. Dockerfile).
     *
     * - Не-HEIC файлы (jpg/png/webp/pdf/видео) возвращаются без изменений.
     * - При недоступности Imagick/декодера исходный путь сохраняется (graceful fallback).
     *
     * Возвращает путь к итоговому .webp либо исходный путь.
     */
    public function normalizeHeicToWebp(?string $path, string $disk = 'public', int $quality = 82): ?string
    {
        if ($path === null || $path === '') {
            return $path;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, self::HEIC_EXTENSIONS, true)) {
            return $path;
        }

        if (! class_exists(\Imagick::class)) {
            Log::warning('ImageConverter: расширение Imagick недоступно, HEIC не сконвертирован.', ['path' => $path]);

            return $path;
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return $path;
        }

        try {
            $imagick = new \Imagick;
            $imagick->readImageBlob($storage->get($path));
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($quality);
            $webpContents = $imagick->getImageBlob();
            $imagick->clear();
        } catch (\Throwable $e) {
            // Наиболее вероятно — отсутствует HEVC-декодер (libheif-plugin-libde265).
            Log::warning('ImageConverter: не удалось декодировать HEIC (нужен libheif+libde265?).', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return $path;
        }

        if ($webpContents === '') {
            return $path;
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $path);

        $storage->put($webpPath, $webpContents, 'public');

        if ($webpPath !== $path) {
            $storage->delete($path);
        }

        return $webpPath;
    }

    /**
     * Декодирует HEIC/HEIF-байты в JPEG-байты (Imagick, первый кадр).
     *
     * Используется при загрузке в Spatie Media Library: heic нельзя хранить как
     * оригинал (Imagick не умеет кодировать heic, а медиатека именует temp-файл
     * конверсии по расширению оригинала → пустые webp/avif). Поэтому heic
     * нормализуется в JPEG ДО сохранения, и уже с него генерируются webp/avif.
     *
     * HEIC часто содержит 2 кадра (основной + превью) — берём первый (setIteratorIndex(0)),
     * т.к. JPEG однокадровый и запись всех кадров даёт пустой файл.
     *
     * Возвращает JPEG-байты либо null (Imagick/декодер недоступен или ошибка).
     */
    public function heicBytesToJpeg(string $bytes, int $quality = 85): ?string
    {
        if (! class_exists(\Imagick::class)) {
            Log::warning('ImageConverter: расширение Imagick недоступно, HEIC не сконвертирован.');

            return null;
        }

        try {
            $imagick = new \Imagick;
            $imagick->readImageBlob($bytes);
            $imagick->setIteratorIndex(0);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality($quality);
            $jpeg = $imagick->getImageBlob();
            $imagick->clear();
        } catch (\Throwable $e) {
            // Наиболее вероятно — отсутствует HEVC-декодер (libheif-plugin-libde265).
            Log::warning('ImageConverter: не удалось декодировать HEIC в JPEG (нужен libheif+libde265?).', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $jpeg !== '' ? $jpeg : null;
    }
}
