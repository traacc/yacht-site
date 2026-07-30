<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Состояние объявления.
 *
 * Pending — подано и ждёт модерации (ТЗ: «объявления вначале проходят
 * модерацию»); правка опубликованного объявления возвращает его сюда же.
 * Sold и Archived — снятие с публикации самим автором: Sold остаётся видимым
 * с плашкой «Продано», Archived исчезает с витрины совсем.
 */
enum AdvertStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Sold = 'sold';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'На модерации',
            self::Published => 'Опубликовано',
            self::Rejected => 'Отклонено',
            self::Sold => 'Продано',
            self::Archived => 'Снято с публикации',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Published => 'success',
            self::Rejected => 'danger',
            self::Sold => 'info',
            self::Archived => 'gray',
        };
    }

    /** Видно ли объявление на публичной витрине. */
    public function isVisible(): bool
    {
        return $this === self::Published || $this === self::Sold;
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
