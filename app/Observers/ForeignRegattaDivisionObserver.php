<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Service\SyncFleetDivisionYachts;
use App\Models\ForeignRegattaDivision;
use App\Models\ForeignRegattaYacht;

/**
 * Держит лодки дивизиона в согласии с самим дивизионом.
 *
 * Дивизионы правятся репитером в форме регаты, а лодки — отдельным ресурсом,
 * поэтому синхронизация количества безопасна: вложенного репитера, который бы
 * стёр только что созданные строки как «отсутствующие в состоянии», здесь нет.
 *
 * Регистрируется в AppServiceProvider:
 *   ForeignRegattaDivision::observe(ForeignRegattaDivisionObserver::class);
 */
class ForeignRegattaDivisionObserver
{
    public function __construct(private readonly SyncFleetDivisionYachts $sync) {}

    public function saved(ForeignRegattaDivision $division): void
    {
        // restore() сохраняет модель до события `restored`, и в этот момент все
        // лодки дивизиона ещё в корзине: синхронизация насчитала бы ноль живых
        // и завела бы новые поверх тех, что сейчас вернутся.
        if ($division->wasChanged('deleted_at')) {
            return;
        }

        $this->sync->handle($division);
    }

    /**
     * Мягкое удаление дивизиона уносит его лодки: FK-каскад срабатывает только
     * на жёстком DELETE, а `deleting` при soft delete до базы не доходит.
     */
    public function deleted(ForeignRegattaDivision $division): void
    {
        if ($division->isForceDeleting()) {
            return;
        }

        $division->yachts()->get()->each(
            fn (ForeignRegattaYacht $yacht) => $yacht->delete(),
        );
    }

    public function restored(ForeignRegattaDivision $division): void
    {
        $division->yachts()->onlyTrashed()->get()->each(
            fn (ForeignRegattaYacht $yacht) => $yacht->restore(),
        );

        // Из корзины вернутся и заготовки, удалённые когда-то уменьшением
        // количества, — сверяем число лодок с заявленным ещё раз.
        $this->sync->handle($division);
    }
}
