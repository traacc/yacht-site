<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Доска объявлений.
 *
 * Барахолка и «Продать яхту» — подразделы раздела «Carter 30» (ТЗ 3-го этапа,
 * п. 5). Четыре биржи раздела «Соревнования» (п. 8) добавляются сюда кейсом
 * плюс своим набором полей — модель, премодерация, фото, контакты и переписка
 * с автором у всех досок общие.
 */
enum AdvertType: string
{
    case Marketplace = 'marketplace';
    case YachtSale = 'yacht_sale';

    public function label(): string
    {
        return match ($this) {
            self::Marketplace => 'Барахолка',
            self::YachtSale => 'Продать яхту',
        };
    }

    /** Заголовок витрины. */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Marketplace => 'Барахолка',
            self::YachtSale => 'Яхты на продажу',
        };
    }

    /** Имя роута витрины; страница объявления — то же имя с суффиксом `-item`. */
    public function routeName(): string
    {
        return match ($this) {
            self::Marketplace => 'carter30.marketplace',
            self::YachtSale => 'carter30.yacht-sale',
        };
    }

    public function itemRouteName(): string
    {
        return $this->routeName().'-item';
    }

    /** Максимум фотографий в объявлении (ТЗ бирж: 5 или 10 в зависимости от доски). */
    public function maxPhotos(): int
    {
        return match ($this) {
            self::Marketplace, self::YachtSale => 10,
        };
    }

    /** Нужен ли справочник категорий: «продажа чего угодно» без него не ищется. */
    public function usesCategories(): bool
    {
        return $this === self::Marketplace;
    }

    /** Привязывается ли объявление к зарегистрированной яхте. */
    public function usesYacht(): bool
    {
        return $this === self::YachtSale;
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
