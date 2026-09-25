<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\ParticipationOption;

/**
 * Занятость по типам мест: сколько объявлено и сколько уже разобрали.
 *
 * Общий счёт для дивизиона и для лодки — считают они одинаково, отличается
 * только то, откуда берётся «всего»: у монотипа без выбора яхт это поля
 * дивизиона, у остальных — поля каждой лодки (@see occupancyTotal()).
 *
 * Пустое «всего» означает «вариант не объявлен», а не «мест нет»: пока админ
 * не сказал, сколько их, считать нечего.
 */
trait CountsFleetOccupancy
{
    /** Сколько всего мест этого типа объявлено; null — вариант не объявлен. */
    abstract public function occupancyTotal(ParticipationOption $option): ?int;

    /** Сколько уже занято. */
    public function occupancyTaken(ParticipationOption $option): int
    {
        return max(0, (int) $this->getAttribute($option->takenColumn()));
    }

    /** Сколько осталось; null — вариант не объявлен. */
    public function occupancyLeft(ParticipationOption $option): ?int
    {
        $total = $this->occupancyTotal($option);

        return $total === null ? null : max(0, $total - $this->occupancyTaken($option));
    }

    /** Объявлен ли вариант вообще. */
    public function countsOccupancy(ParticipationOption $option): bool
    {
        return $this->occupancyTotal($option) !== null;
    }

    /** Всё разобрано: места объявлены, но свободных не осталось. */
    public function isSoldOut(ParticipationOption $option): bool
    {
        $left = $this->occupancyLeft($option);

        return $left !== null && $left === 0;
    }

    /** Есть ли что предложить по этому варианту. */
    public function hasVacancy(ParticipationOption $option): bool
    {
        $left = $this->occupancyLeft($option);

        return $left !== null && $left > 0;
    }

    /**
     * «занято 4 из 5, осталось 1 место» — подпись для витрины и админки.
     *
     * Ничего не возвращает, пока вариант не объявлен: писать «осталось 0 из 0»
     * бессмысленно.
     */
    public function occupancyLabel(ParticipationOption $option): ?string
    {
        $total = $this->occupancyTotal($option);

        if ($total === null) {
            return null;
        }

        $taken = min($this->occupancyTaken($option), $total);
        $left = $total - $taken;

        if ($left === 0) {
            return 'всё занято';
        }

        return 'занято '.$taken.' из '.$total.', осталось '.$option->unitLabel($left);
    }

    /** «3 из 5» — короткая подпись для таблиц админки. */
    public function occupancyShortLabel(ParticipationOption $option): ?string
    {
        $total = $this->occupancyTotal($option);

        return $total === null
            ? null
            : min($this->occupancyTaken($option), $total).' из '.$total;
    }
}
