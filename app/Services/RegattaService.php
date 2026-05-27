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
     * Возвращает новое событие.
     *
     * Идемпотентно: если регата уже перенесена (postponed_to_regatta_id заполнен),
     * новая регата не создаётся — возвращается уже существующая.
     */
    public function postpone(Regatta $regatta, Carbon $newDate): Regatta
    {
        if ($regatta->isActive()) {
            throw new \LogicException("Нельзя перенести уже идущую регату.");
        }

        // Идемпотентность: если новая регата уже создана — просто вернуть её
        if ($regatta->postponed_to_regatta_id !== null) {
            $existing = Regatta::find($regatta->postponed_to_regatta_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($regatta, $newDate) {

            // Вычисляем длительность регаты (разница между date_end и date_start)
            $duration = $regatta->date_start->diffInDays($regatta->date_end);

            // 1. Создаём новое событие на новую дату
            $newRegatta = $regatta->replicate([
                'regatta_status',
                'postponed_to_date',
                'postponed_to_regatta_id',
            ]);
            $newRegatta->id             = null; // HasUuids: сброс UUID — creating-хук сгенерирует новый
            $newRegatta->external_id    = null; // creating-хук сгенерирует новый
            $newRegatta->date_start     = $newDate;
            $newRegatta->date_end       = (clone $newDate)->addDays($duration);
            $newRegatta->regatta_status = RegattaStatus::Upcoming;
            $newRegatta->save();

            // 2. Помечаем старое событие как перенесённое
            $regatta->update([
                'regatta_status'          => RegattaStatus::Postponed,
                'postponed_to_date'       => $newDate,
                'postponed_to_regatta_id' => $newRegatta->id,
            ]);

            return $newRegatta;
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
