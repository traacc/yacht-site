<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Приведение загруженного видео к web-совместимому MP4 (H.264, faststart).
 *
 * MOV с iPhone обычно закодирован в HEVC, который Chrome/Firefox часто не
 * воспроизводят. Работает с файлами, уже сохранёнными на диске (относительный путь).
 * Требует ffmpeg/ffprobe в контейнере (ставятся в docker/8.5/Dockerfile).
 */
class VideoConverter
{
    /**
     * Контейнеры, которые всегда пересобираются в .mp4.
     *
     * @var array<string>
     */
    private const CONVERTIBLE = ['mov', 'qt', 'm4v'];

    /** Максимальная ширина итогового видео (фон hero — Full HD). */
    private const MAX_WIDTH = 1920;

    /** Лимит на одну конвертацию, секунд. */
    private const TIMEOUT = 300;

    /**
     * Преобразует видео по указанному пути в web-совместимый MP4.
     *
     * - mov/m4v: H.264 перепаковывается без перекодирования, прочие кодеки (HEVC) — перекодируются;
     * - mp4: перекодируется, только если видеопоток не H.264;
     * - webm, изображения и прочее возвращаются без изменений;
     * - при недоступности ffmpeg или ошибке исходный путь сохраняется (graceful fallback).
     *
     * По умолчанию звук удаляется (видео — беззвучный фон); с $keepAudio первая
     * звуковая дорожка сохраняется и кодируется в AAC (понимают все браузеры).
     * Возвращает путь к итоговому файлу (новый .mp4 либо исходный).
     */
    public function toWebMp4(?string $path, string $disk = 'public', bool $keepAudio = false): ?string
    {
        if ($path === null || $path === '') {
            return $path;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, [...self::CONVERTIBLE, 'mp4'], true)) {
            return $path;
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return $path;
        }

        $source = $storage->path($path);
        $codec = $this->videoCodec($source);

        if ($codec === null) {
            return $path;
        }

        // Уже web-совместимый mp4 — не трогаем (иначе перекодировали бы при каждом сохранении).
        if ($extension === 'mp4' && $codec === 'h264') {
            return $path;
        }

        $targetPath = $this->targetPath($path, $storage);
        $target = $storage->path($targetPath);

        // «?» — дорожка необязательна: ролик без звука тоже сконвертируется.
        $audio = $keepAudio
            ? ['-map', '0:a:0?', '-c:a', 'aac', '-b:a', '128k']
            : ['-an'];

        $command = $codec === 'h264'
            // H.264 — только перепаковка контейнера, это секунды.
            ? ['ffmpeg', '-y', '-i', $source, '-map', '0:v:0', '-c:v', 'copy',
                ...$audio, '-movflags', '+faststart', $target]
            : ['ffmpeg', '-y', '-i', $source, '-map', '0:v:0',
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
                '-vf', "scale='min(".self::MAX_WIDTH.",iw)':-2",
                ...$audio, '-movflags', '+faststart', $target];

        set_time_limit(self::TIMEOUT + 60);

        $result = Process::timeout(self::TIMEOUT)->run($command);

        if (! $result->successful() || ! is_file($target) || filesize($target) === 0) {
            Log::warning('VideoConverter: конвертация не удалась, оставляем исходный файл.', [
                'path' => $path,
                'codec' => $codec,
                'error' => mb_substr($result->errorOutput(), -2000),
            ]);

            if (is_file($target)) {
                @unlink($target);
            }

            return $path;
        }

        $storage->delete($path);

        return $targetPath;
    }

    /**
     * Кодек первого видеопотока (h264, hevc, …) либо null, если ffprobe недоступен или потока нет.
     */
    private function videoCodec(string $absolutePath): ?string
    {
        $result = Process::timeout(30)->run([
            'ffprobe', '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'stream=codec_name', '-of', 'default=nw=1:nk=1',
            $absolutePath,
        ]);

        if (! $result->successful()) {
            Log::warning('VideoConverter: ffprobe не смог прочитать файл.', [
                'path' => $absolutePath,
                'error' => mb_substr($result->errorOutput(), -1000),
            ]);

            return null;
        }

        $codec = strtolower(trim($result->output()));

        return $codec !== '' ? $codec : null;
    }

    /**
     * Свободный путь для .mp4 рядом с исходником (mp4 → mp4 получает суффикс).
     */
    private function targetPath(string $path, Filesystem $storage): string
    {
        $dir = pathinfo($path, PATHINFO_DIRNAME);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $prefix = ($dir === '.' || $dir === '') ? '' : $dir.'/';

        $candidate = $prefix.$base.'.mp4';
        $i = 1;

        while ($storage->exists($candidate)) {
            $candidate = $prefix.$base.'-web'.($i > 1 ? '-'.$i : '').'.mp4';
            $i++;
        }

        return $candidate;
    }
}
