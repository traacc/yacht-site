<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * По какой дате платёж попадает в финансовый отчёт за период.
 *
 * Бухгалтеру нужны оба среза: «когда деньги пришли» (дата оплаты) и
 * «когда приход акцептован» (дата подтверждения) — они расходятся,
 * когда наличные вносятся в реестр задним числом.
 */
enum FinancialDateBasis: string implements HasLabel
{
    case PaidAt = 'paid_at';
    case ConfirmedAt = 'confirmed_at';

    public function label(): string
    {
        return match ($this) {
            self::PaidAt => 'По дате оплаты',
            self::ConfirmedAt => 'По дате подтверждения прихода',
        };
    }

    /** Колонка payment_registries, по которой отбирается период. */
    public function column(): string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
