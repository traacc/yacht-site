<?php

namespace App\Support;

use Symfony\Component\Mime\MimeTypes;

/**
 * Человекочитаемый список форматов для полей загрузки: MIME-типы из
 * acceptedFileTypes() → «JPG, PNG, WEBP, MP4».
 */
class UploadFormats
{
    private const WILDCARDS = [
        'image/*' => 'любые изображения',
        'video/*' => 'любые видео',
        'audio/*' => 'любое аудио',
    ];

    /**
     * Подпись «Форматы: …» — только для полей, принимающих изображения или видео.
     *
     * @param  array<string>|null  $mimeTypes
     */
    public static function label(?array $mimeTypes): ?string
    {
        if (blank($mimeTypes)) {
            return null;
        }

        $hasMedia = collect($mimeTypes)->contains(
            fn (string $type): bool => str_starts_with($type, 'image/') || str_starts_with($type, 'video/')
        );

        if (! $hasMedia) {
            return null;
        }

        $formats = static::list($mimeTypes);

        return $formats === [] ? null : 'Форматы: '.implode(', ', $formats);
    }

    /**
     * @param  array<string>  $mimeTypes
     * @return array<string>
     */
    public static function list(array $mimeTypes): array
    {
        $mimes = MimeTypes::getDefault();

        return collect($mimeTypes)
            ->map(function (string $type) use ($mimes): ?string {
                $type = strtolower(trim($type));

                if (isset(self::WILDCARDS[$type])) {
                    return self::WILDCARDS[$type];
                }

                // Расширение вида «.rgd» вместо MIME.
                if (str_starts_with($type, '.')) {
                    return strtoupper(ltrim($type, '.'));
                }

                $extension = $mimes->getExtensions($type)[0] ?? null;

                return $extension !== null ? strtoupper($extension) : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
