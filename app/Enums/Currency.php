<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Валюта цены.
 *
 * Зарубежные регаты и чартер считают в евро или долларах, поэтому сумма
 * хранится отдельно от валюты — так же, как подпись «за неделю»
 * (@see CharterPriceUnit). Пустое поле означает рубли: до появления этого
 * выбора все цены были рублёвыми.
 */
enum Currency: string
{
    case Rub = 'RUB';
    case Eur = 'EUR';
    case Usd = 'USD';

    public function label(): string
    {
        return match ($this) {
            self::Rub => 'Рубли',
            self::Eur => 'Евро',
            self::Usd => 'Доллары',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Rub => '₽',
            self::Eur => '€',
            self::Usd => '$',
        };
    }

    /** «12 500 €» — сумма со знаком валюты, как её печатают карточки. */
    public function format(int|float $value): string
    {
        return number_format((float) $value, 0, ',', ' ').' '.$this->symbol();
    }

    /** Валюта по умолчанию: пустое поле в БД — рубли. */
    public static function default(): self
    {
        return self::Rub;
    }

    public static function fromNullable(self|string|null $currency): self
    {
        return match (true) {
            $currency instanceof self => $currency,
            is_string($currency) => self::tryFrom($currency) ?? self::default(),
            default => self::default(),
        };
    }

    /** @return array<string, string> value => label, для Select. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [
                $case->value => $case->label().' ('.$case->symbol().')',
            ])
            ->all();
    }
}
