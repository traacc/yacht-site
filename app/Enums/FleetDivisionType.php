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
 *  - MonotypeChoice — монотип с выбором: модель одна на дивизион и выбирается
 *    из справочника (@see CharterYachtModel), а всё остальное — цены, места,
 *    шкипер, занятость — вводится у каждой лодки, как в гандикапе.
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
            self::MonotypeChoice => 'Модель одна на весь дивизион — выберите её из справочника. Лодки добавляются поимённо, и у каждой свои цены, места, шкипер и занятость — как в гандикапном.',
            self::Handicap => 'Разные лодки: характеристики и цены вводятся у каждой, дивизион несёт только общее описание.',
        };
    }

    /**
     * Общая ли спецификация у лодок дивизиона.
     *
     * У обоих монотипов: лодки одной модели, поэтому модель, описание и галерея
     * задаются один раз на дивизионе и наследуются лодками
     * (@see App\Models\ForeignRegattaYacht::spec()). У гандикапа всё своё.
     */
    public function sharesSpec(): bool
    {
        return $this !== self::Handicap;
    }

    /**
     * Общая ли цена у лодок дивизиона.
     *
     * Только у монотипа без выбора: там лодки взаимозаменяемы и продаются общим
     * пулом. В монотипе с выбором лодки называются поимённо, и цена с занятостью
     * у каждой своя — ровно как в гандикапе.
     */
    public function sharesPrices(): bool
    {
        return $this === self::Monotype;
    }

    /** Заводятся ли лодки автоматически по указанному количеству. */
    public function usesYachtsCount(): bool
    {
        return $this === self::Monotype;
    }

    /** Добавляются ли лодки поимённо. */
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
