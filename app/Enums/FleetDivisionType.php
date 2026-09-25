<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Как устроен дивизион флота зарубежной регаты.
 *
 * Типы отличаются двумя вещами: одинаковые ли лодки (то есть можно ли завести
 * спецификацию один раз на весь дивизион) и общая ли у них цена.
 *
 *  - Monotype — монотип без выбора: все лодки одной модели, она вводится здесь
 *    же руками, а сами лодки заводятся по количеству. Посетителю выбирать не из
 *    чего: лодки различаются только шкипером и свободными местами.
 *  - MonotypeChoice — монотип с выбором: цена общая, но лодки добавляются
 *    поимённо и модели у них могут быть разные — каждая выбирается из
 *    справочника (@see CharterYachtModel).
 *  - Handicap — гандикап: разные лодки со своими характеристиками и своими
 *    ценами, дивизион только группирует их и несёт общее описание.
 */
enum FleetDivisionType: string
{
    case Monotype = 'monotype';
    case MonotypeChoice = 'monotype_choice';
    case Handicap = 'handicap';

    public function label(): string
    {
        return match ($this) {
            self::Monotype => 'Монотипный без выбора яхт',
            self::MonotypeChoice => 'Монотипный с выбором яхт',
            self::Handicap => 'Гандикапный',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Monotype => 'Все лодки одной модели: модель, характеристики и цены задаются здесь один раз, укажите количество — лодки создадутся сами.',
            self::MonotypeChoice => 'Цены общие на весь дивизион, а лодки добавляются поимённо: модель каждой выбирается из справочника и может отличаться.',
            self::Handicap => 'Разные лодки: характеристики и цены вводятся у каждой, дивизион несёт только общее описание.',
        };
    }

    /**
     * Общая ли спецификация у лодок дивизиона.
     *
     * Только у монотипа без выбора: там модель, описание и галерея одни на всех
     * и наследуются лодками (@see App\Models\ForeignRegattaYacht::spec()).
     */
    public function sharesSpec(): bool
    {
        return $this === self::Monotype;
    }

    /**
     * Общая ли цена у лодок дивизиона.
     *
     * У обоих монотипов: лодки одного класса стоят одинаково, даже когда модели
     * у них разные. У гандикапа цена своя у каждой лодки.
     */
    public function sharesPrices(): bool
    {
        return $this !== self::Handicap;
    }

    /** Заводятся ли лодки автоматически по указанному количеству. */
    public function usesYachtsCount(): bool
    {
        return $this === self::Monotype;
    }

    /** Добавляются ли лодки поимённо, с выбором модели из справочника. */
    public function picksYachts(): bool
    {
        return ! $this->usesYachtsCount();
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /** Типы с общей ценой — для запросов к БД. */
    public static function sharingPrices(): array
    {
        return collect(self::cases())
            ->filter(fn (self $case): bool => $case->sharesPrices())
            ->map(fn (self $case): string => $case->value)
            ->values()
            ->all();
    }
}
