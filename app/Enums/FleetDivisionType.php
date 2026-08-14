<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Как объявлен дивизион флота зарубежной регаты.
 *
 * Fleet — «флот из N одинаковых лодок»: характеристики задаются один раз на
 * дивизионе, лодки заводятся автоматически по количеству и наследуют их.
 * YachtList — «список конкретных лодок»: дивизион только группирует и именует,
 * характеристики каждая лодка несёт сама.
 *
 * Разница ровно в одном — где живёт спецификация, поэтому это тип дивизиона,
 * а не две разные сущности: на витрине и то и другое выглядит как карточки лодок.
 */
enum FleetDivisionType: string
{
    case Fleet = 'fleet';
    case YachtList = 'list';

    public function label(): string
    {
        return match ($this) {
            self::Fleet => 'Флот одинаковых яхт',
            self::YachtList => 'Список конкретных яхт',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Fleet => 'Характеристики задаются здесь и одинаковы для всех лодок дивизиона; укажите количество — лодки создадутся сами.',
            self::YachtList => 'Дивизион только группирует лодки; характеристики заводятся у каждой лодки отдельно.',
        };
    }

    /** Наследуют ли лодки характеристики дивизиона. */
    public function sharesSpec(): bool
    {
        return $this === self::Fleet;
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
