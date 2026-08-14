<?php

declare(strict_types=1);

namespace App\Actions\Service;

use App\Models\ForeignRegattaDivision;
use App\Models\ForeignRegattaYacht;
use Illuminate\Database\Eloquent\Collection;

/**
 * Приводит число лодок дивизиона-флота к заявленному `yachts_count`.
 *
 * Строки нужны физически, даже когда все лодки одинаковые: шкипер, свободные
 * места и занятость — свойство конкретной лодки, а не дивизиона. Модель, цену
 * и остальную спецификацию заготовки не копируют, а наследуют
 * (@see App\Models\ForeignRegattaYacht::spec()), поэтому правка дивизиона
 * меняет все его карточки разом.
 *
 * Лишние строки удаляются только с конца и только нетронутые: у лодки, которой
 * уже назначили шкипера или места, за уменьшением количества стоит ошибка
 * админа, а не намерение стереть данные.
 */
final class SyncFleetDivisionYachts
{
    public function handle(ForeignRegattaDivision $division): void
    {
        if (! $division->sharesSpec()) {
            return;
        }

        $target = max(0, (int) ($division->yachts_count ?? 0));
        $existing = $division->yachts()->get();

        $difference = $target - $existing->count();

        if ($difference > 0) {
            $this->create($division, $existing->count(), $difference);

            return;
        }

        if ($difference < 0) {
            $this->removeStubs($existing, -$difference);
        }
    }

    /**
     * @param  int  $from  сколько лодок уже есть — с этого номера продолжаем нумерацию
     */
    private function create(ForeignRegattaDivision $division, int $from, int $count): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $number = $from + $index;

            ForeignRegattaYacht::create([
                'foreign_regatta_id' => $division->foreign_regatta_id,
                'division_id' => $division->getKey(),
                // Имя-заготовка: чартер обычно сообщает названия лодок позже,
                // до тех пор их нужно как-то различать в списке.
                'name' => '№'.$number,
                'sort_order' => $number,
            ]);
        }
    }

    /**
     * @param  Collection<int, ForeignRegattaYacht>  $existing
     */
    private function removeStubs(Collection $existing, int $count): void
    {
        $existing
            ->reverse()
            ->filter(fn (ForeignRegattaYacht $yacht): bool => $yacht->isUntouchedStub())
            ->take($count)
            ->each(fn (ForeignRegattaYacht $yacht) => $yacht->delete());
    }
}
