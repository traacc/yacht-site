<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Как «Конструктор мероприятия» узнаёт, сколько яхт есть на даты заказчика.
 *
 * Проверка наличия честна, только когда владельцы заводят периоды аренды.
 * Пока арендный флот в базе не ведётся, она на любую дату отвечает «свободно
 * 0 яхт» — поэтому есть справочный режим: число яхт и их стоимость берутся из
 * настроек, а наличие менеджер уточняет вручную.
 */
enum EventFleetMode: string
{
    /** Проверять, если на период дат объявлена аренда хоть одной яхты. */
    case Auto = 'auto';

    case Check = 'check';

    case Reference = 'reference';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Авто — проверять, если арендный флот заведён на эти даты',
            self::Check => 'Всегда проверять свободные яхты',
            self::Reference => 'Не проверять — считать по справочной цене',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode): array => [$mode->value => $mode->label()])
            ->all();
    }
}
