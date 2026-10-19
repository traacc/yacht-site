<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Вычисляемое состояние новости на сайте (не колонка БД):
 * выводится из published_at и unpublished_at, см. News::publicationStatus().
 */
enum NewsPublicationStatus: string
{
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Unpublished = 'unpublished';

    public function label(): string
    {
        return match ($this) {
            self::Published => 'На сайте',
            self::Scheduled => 'Запланирована',
            self::Unpublished => 'Снята с публикации',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Scheduled => 'warning',
            self::Unpublished => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
