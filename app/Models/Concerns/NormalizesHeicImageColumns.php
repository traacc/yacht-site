<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\ImageConverter;

/**
 * Нормализует HEIC/HEIF в WebP для строковых колонок с путём к изображению.
 *
 * HEIC не отображается браузерами, поэтому загруженный в простое поле-путь файл
 * (напр. news.cover_image_url, user.photo_url) на сохранении перекодируется в webp,
 * а колонка обновляется на новый путь. Не-HEIC значения (jpg/png/webp/pdf) не трогаются
 * (см. ImageConverter::normalizeHeicToWebp).
 *
 * Модель объявляет список колонок:
 *   protected array $heicImageColumns = ['cover_image_url'];
 */
trait NormalizesHeicImageColumns
{
    public static function bootNormalizesHeicImageColumns(): void
    {
        static::saving(function ($model): void {
            foreach ($model->heicImageColumns ?? [] as $column) {
                if (! $model->isDirty($column)) {
                    continue;
                }

                $value = $model->getAttribute($column);

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $normalized = app(ImageConverter::class)->normalizeHeicToWebp($value);

                if ($normalized !== $value) {
                    $model->setAttribute($column, $normalized);
                }
            }
        });
    }
}
