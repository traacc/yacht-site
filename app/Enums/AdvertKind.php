<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Вид объявления на бирже: предложение услуги/товара или запрос на поиск.
 *
 * Дуальность есть не у всех досок (@see AdvertType::kinds()): «Экипажи для
 * соревнований» и «Яхты для соревнований» односторонние, там колонка остаётся
 * пустой. Подписи под конкретную биржу даёт AdvertType::kindLabel().
 *
 * На бирже парусов предложение разделено на продажу и аренду (Sale / Rent):
 * от этого зависят единица цены и залог. Остальные дуальные доски пользуются
 * общим Offer.
 */
enum AdvertKind: string
{
    case Offer = 'offer';
    case Sale = 'sale';
    case Rent = 'rent';
    case Request = 'request';

    public function label(): string
    {
        return match ($this) {
            self::Offer => 'Предложение',
            self::Sale => 'Продажа',
            self::Rent => 'Аренда',
            self::Request => 'Запрос',
        };
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
