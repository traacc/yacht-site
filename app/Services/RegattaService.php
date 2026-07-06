<?php

namespace App\Services;

use App\Enums\RegattaStatus;
use App\Models\Regatta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RegattaService
{
    /**
     * Перенести событие на новую дату.
     *
     * Политика переноса:
     *  - оригинальная регата остаётся «живым» событием — у неё просто меняется
     *    дата (id, external_id, URL, заявки, документы и расписание сохраняются);
     *  - создаётся снимок со статусом «Перенесена», который хранит СТАРЫЕ даты
     *    и служит исторической отметкой о переносе.
     *
     * Возвращает обновлённую (перенесённую) регату — то же самое событие.
     *
     * Идемпотентно: если регата уже стоит на нужной дате — снимок не создаётся.
     */
    public function postpone(Regatta $regatta, Carbon $newDate): Regatta
    {
        if ($regatta->isActive()) {
            throw new \LogicException("Нельзя перенести уже идущую регату.");
        }

        // Идемпотентность: регата уже стоит на нужной дате — повторно не переносим
        if ($regatta->date_start && $regatta->date_start->isSameDay($newDate)) {
            return $regatta;
        }

        return DB::transaction(function () use ($regatta, $newDate) {

            // Вычисляем длительность регаты (разница между date_end и date_start)
            $duration = $regatta->date_start->diffInDays($regatta->date_end);

            // 1. Снимок со статусом «Перенесена» — хранит СТАРЫЕ даты события
            //    и ссылается на «живую» регату.
            $snapshot = $regatta->replicate([
                'regatta_status',
                'postponed_to_date',
                'postponed_note',
                'postponed_to_regatta_id',
            ]);
            $snapshot->id                      = null; // HasUuids: сброс UUID — creating-хук сгенерирует новый
            $snapshot->external_id             = null; // creating-хук сгенерирует новый
            $snapshot->date_start              = $regatta->date_start; // старые даты
            $snapshot->date_end                = $regatta->date_end;
            $snapshot->regatta_status          = RegattaStatus::Postponed;
            $snapshot->postponed_to_date       = $newDate;
            $snapshot->postponed_to_regatta_id = $regatta->id;
            $snapshot->save();

            // 2. Оригинал остаётся живым событием — просто меняем дату
            $regatta->update([
                'date_start'     => $newDate,
                'date_end'       => (clone $newDate)->addDays($duration),
                'regatta_status' => RegattaStatus::Upcoming,
            ]);

            return $regatta;
        });
    }

    /**
     * Отменить событие.
     */
    public function cancel(Regatta $regatta): void
    {
        if ($regatta->isActive()) {
            throw new \LogicException("Нельзя отменить уже идущую регату.");
        }

        $regatta->update([
            'regatta_status' => RegattaStatus::Cancelled,
        ]);
    }
}
