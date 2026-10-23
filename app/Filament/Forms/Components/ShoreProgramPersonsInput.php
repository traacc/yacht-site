<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Models\Regatta;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Поле «Береговая программа, человек» в формах заявки (админка и ЛК).
 *
 * Показывается, только если у выбранной регаты есть сбор за береговую
 * программу; читает соседние поля `regatta_id` и `crew`. Счёт по введённому
 * числу выставляет RegattaEntryShoreProgramObserver.
 */
class ShoreProgramPersonsInput extends TextInput
{
    /** Ограничивать число человек размером экипажа из репитера `crew`. */
    protected bool $limitedByCrew = true;

    public static function make(?string $name = 'shore_program_persons'): static
    {
        return parent::make($name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Береговая программа, человек')
            ->integer()
            ->minValue(1)
            ->maxValue(fn (Get $get): ?int => $this->limitedByCrew
                ? max(count($get('crew') ?? []), 1)
                : null)
            ->visible(fn (Get $get): bool => (bool) static::regatta($get)?->hasShoreProgramFee())
            ->required(fn (Get $get): bool => (bool) static::regatta($get)?->hasShoreProgramFee())
            ->live(onBlur: true)
            ->helperText(function (Get $get, mixed $state): ?string {
                $regatta = static::regatta($get);

                if (! $regatta?->hasShoreProgramFee()) {
                    return null;
                }

                $price = number_format((float) $regatta->shore_fee_per_person, 2, ',', ' ');
                $hint = "{$price} ₽ за человека";

                if ($this->limitedByCrew) {
                    $hint .= ', не больше, чем людей в экипаже';
                }

                $persons = is_numeric($state) ? (int) $state : 0;

                return $persons > 0
                    ? $hint.'. К оплате: '.number_format((float) $regatta->shoreProgramPrice($persons), 2, ',', ' ').' ₽.'
                    : $hint.'.';
            });
    }

    /** Без предела по экипажу — для администратора, который правит заявку вручную. */
    public function withoutCrewLimit(): static
    {
        $this->limitedByCrew = false;

        return $this;
    }

    private static function regatta(Get $get): ?Regatta
    {
        $regattaId = $get('regatta_id');

        return is_string($regattaId) && $regattaId !== ''
            ? Regatta::find($regattaId)
            : null;
    }
}
