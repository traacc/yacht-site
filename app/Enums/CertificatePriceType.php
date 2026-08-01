<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Как назначена цена подарочного сертификата (ТЗ 3-го этапа, п. 7).
 *
 * Фиксированная — сертификат на конкретную услугу за конкретную сумму.
 * Диапазонная — заказчик сам выбирает номинал в объявленных границах; из
 * границ и шага собирается список сумм в форме заказа
 * (@see \App\Models\GiftCertificate::nominalOptions()).
 */
enum CertificatePriceType: string
{
    case Fixed = 'fixed';
    case Range = 'range';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Фиксированная',
            self::Range => 'Диапазон номиналов',
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
