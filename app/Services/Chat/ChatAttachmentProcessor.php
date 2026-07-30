<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Services\ImageConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Подготовка вложений чата к сохранению.
 *
 * Что здесь происходит:
 *  - HEIC/HEIF нормализуется в JPEG ДО сохранения. Livewire-загрузка идёт мимо
 *    SpatieMediaLibraryFileUpload::configureUsing (там это делается для форм
 *    Filament), поэтому конвертацию вызываем руками — иначе heic останется
 *    оригиналом и конверсия preview выйдет пустой.
 *  - Фото пользователей пережимаются: по ТЗ загружаемые пользователями
 *    изображения не должны утяжелять сайт (ориентир 50–70 КБ). У операторов
 *    ограничений нет — им бывает нужно отправить читаемый скан.
 *
 * @see ImageConverter::heicBytesToJpeg()
 */
class ChatAttachmentProcessor
{
    /** Верхняя граница диапазона из ТЗ. */
    private const TARGET_BYTES = 70 * 1024;

    /** Ширина, с которой начинаем пережимать, и запасные шаги при промахе. */
    private const WIDTH_STEPS = [1600, 1200, 1000];

    private const QUALITY_STEPS = [80, 70, 60, 50, 40, 30];

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const HEIC_MIMES = ['image/heic', 'image/heif'];

    private const HEIC_EXTENSIONS = ['heic', 'heif'];

    public function __construct(
        private readonly ImageConverter $converter,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  bool  $compress  Пережимать изображения (для клиентов — да, для операторов — нет).
     * @return list<array{bytes: string, filename: string, mime: string}>
     */
    public function process(array $files, bool $compress): array
    {
        $prepared = [];

        foreach ($files as $file) {
            $item = $this->prepare($file, $compress);

            if ($item !== null) {
                $prepared[] = $item;
            }
        }

        return $prepared;
    }

    /** @return array{bytes: string, filename: string, mime: string}|null */
    private function prepare(UploadedFile $file, bool $compress): ?array
    {
        $bytes = $file->get();

        if ($bytes === false || $bytes === '') {
            return null;
        }

        $filename = $file->getClientOriginalName();
        $mime = (string) $file->getMimeType();

        if ($this->isHeic($file, $mime)) {
            $jpeg = $this->converter->heicBytesToJpeg($bytes);

            if ($jpeg === null) {
                // Декодер недоступен — сохраняем как есть: потерять файл хуже,
                // чем показать его без превью.
                Log::warning('ChatAttachmentProcessor: HEIC не сконвертирован, сохраняем оригинал.', [
                    'filename' => $filename,
                ]);
            } else {
                $bytes = $jpeg;
                $filename = preg_replace('/\.[^.]+$/', '.jpg', $filename) ?? $filename;
                $mime = 'image/jpeg';
            }
        }

        if ($compress && in_array($mime, self::IMAGE_MIMES, true) && strlen($bytes) > self::TARGET_BYTES) {
            $bytes = $this->compress($bytes, $filename);
            $mime = 'image/jpeg';
        }

        return [
            'bytes' => $bytes,
            // Расширение приводим к фактическому типу: клиент мог прислать jpeg
            // под именем «file.svg», и сохранённое имя вводило бы в заблуждение
            // и браузер, и оператора.
            'filename' => $this->normalizeExtension($filename, $mime),
            'mime' => $mime,
        ];
    }

    /** Приводит расширение имени файла к фактическому mime-типу. */
    private function normalizeExtension(string $filename, string $mime): string
    {
        $expected = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'application/pdf' => 'pdf',
            default => null,
        };

        if ($expected === null) {
            return $filename;
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);

        return ($base === '' ? 'file' : $base).'.'.$expected;
    }

    /**
     * Уменьшает изображение до целевого размера.
     *
     * Если уложиться не удалось — возвращаем самый маленький полученный
     * результат: отправку сообщения из-за этого ронять нельзя.
     */
    private function compress(string $bytes, string $filename): string
    {
        $best = $bytes;

        foreach (self::WIDTH_STEPS as $width) {
            foreach (self::QUALITY_STEPS as $quality) {
                $candidate = $this->encode($bytes, $width, $quality);

                if ($candidate === null) {
                    // Драйвер изображений недоступен — дальше пробовать бессмысленно.
                    return $best;
                }

                if (strlen($candidate) < strlen($best)) {
                    $best = $candidate;
                }

                if (strlen($candidate) <= self::TARGET_BYTES) {
                    return $candidate;
                }
            }
        }

        Log::info('ChatAttachmentProcessor: не удалось уложиться в целевой размер вложения.', [
            'filename' => $filename,
            'bytes' => strlen($best),
        ]);

        return $best;
    }

    private function encode(string $bytes, int $width, int $quality): ?string
    {
        $source = tempnam(sys_get_temp_dir(), 'chat-src');
        $target = tempnam(sys_get_temp_dir(), 'chat-dst').'.jpg';

        try {
            file_put_contents($source, $bytes);

            Image::load($source)
                ->fit(Fit::Max, $width, $width)
                ->quality($quality)
                ->save($target);

            $result = file_get_contents($target);

            return $result === false ? null : $result;
        } catch (\Throwable $e) {
            Log::warning('ChatAttachmentProcessor: не удалось пережать изображение.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    private function isHeic(UploadedFile $file, string $mime): bool
    {
        $extension = strtolower((string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));

        return in_array($extension, self::HEIC_EXTENSIONS, true)
            || in_array($mime, self::HEIC_MIMES, true);
    }
}
