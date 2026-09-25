<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Plural;

/**
 * Вариант участия в зарубежной регате (ТЗ 3-го этапа, п. 7).
 *
 * Регата объявляет, какие варианты предлагает (`participation_options`), а
 * заявка выбирает один из объявленных — поэтому подписи живут в одном месте,
 * а не отдельно в форме и отдельно в админке.
 *
 * Набор фиксированный: у каждого варианта своя цена и свой счётчик занятости
 * (@see App\Models\Concerns\CountsFleetOccupancy), поэтому «ещё один тип
 * мест» — это правка кода, а не запись в справочнике.
 */
enum ParticipationOption: string
{
    case Seat = 'seat';
    case SoloSeat = 'solo_seat';
    case Cabin = 'cabin';
    case Yacht = 'yacht';

    public function label(): string
    {
        return match ($this) {
            self::Seat => 'Место в двухместной каюте',
            self::SoloSeat => 'Место в одноместной каюте',
            self::Cabin => 'Двухместная каюта',
            self::Yacht => 'Яхта целиком',
        };
    }

    /** Подпись кнопки у лодки: рядом с ней встаёт цена варианта. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Seat => 'Место',
            self::SoloSeat => 'Место в одноместной',
            self::Cabin => 'Каюта',
            self::Yacht => 'Яхта целиком',
        };
    }

    /**
     * Поле заявки, в которое кнопка подставляет выбранное предложение.
     *
     * Поле у каждого варианта своё: списки разные — где-то места ещё есть, а
     * лодка целиком уже занята. Значение — лодка или дивизион
     * (@see App\Models\ForeignRegatta::serviceOptions()).
     *
     * @see ServiceType::declaredPayloadFields()
     */
    public function payloadField(): string
    {
        return match ($this) {
            self::Seat => 'crew_yacht',
            self::SoloSeat => 'solo_seat_yacht',
            self::Cabin => 'cabin_yacht',
            self::Yacht => 'charter_yacht',
        };
    }

    /** Цена этого варианта — колонка у дивизиона и у лодки. */
    public function priceColumn(): string
    {
        return match ($this) {
            self::Seat => 'seat_price',
            self::SoloSeat => 'solo_seat_price',
            self::Cabin => 'cabin_price',
            self::Yacht => 'price',
        };
    }

    /** Колонки счётчика занятости: сколько всего и сколько занято. */
    public function totalColumn(): string
    {
        return match ($this) {
            self::Seat => 'seats_total',
            self::SoloSeat => 'solo_seats_total',
            self::Cabin => 'cabins_total',
            self::Yacht => 'yachts_total',
        };
    }

    public function takenColumn(): string
    {
        return match ($this) {
            self::Seat => 'seats_taken',
            self::SoloSeat => 'solo_seats_taken',
            self::Cabin => 'cabins_taken',
            self::Yacht => 'yachts_taken',
        };
    }

    /** Места в экипаж — в отличие от лодки целиком. */
    public function isSeatLike(): bool
    {
        return $this !== self::Yacht;
    }

    /** Склонение единицы варианта: «2 места», «1 каюта», «3 яхты». */
    public function unitLabel(int $count): string
    {
        return match ($this) {
            self::Seat, self::SoloSeat => Plural::with($count, 'место', 'места', 'мест'),
            self::Cabin => Plural::with($count, 'каюта', 'каюты', 'кают'),
            self::Yacht => Plural::with($count, 'яхта', 'яхты', 'яхт'),
        };
    }

    /** @return array<string, string> value => label, для Select и чекбоксов. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
