<?php

namespace App\Observers;

use App\Models\RegattaEntry;
use Illuminate\Validation\ValidationException;

/**
 * Отслеживает события заявки на регату.
 * Регистрируется в AppServiceProvider:
 *   RegattaEntry::observe(RegattaEntryObserver::class);
 */
class RegattaEntryObserver
{
    /**
     * Перед сохранением: проверяем занятость яхты.
     * Если яхта уже участвует в другой регате в этот период — бросаем исключение.
     *
     * @throws ValidationException
     */
    public function saving(RegattaEntry $entry): void
    {
        if (! $entry->yacht_id) {
            return;
        }

        $yacht   = $entry->yacht;
        $regatta = $entry->regatta;

        $conflict = RegattaEntry::query()
            ->where('yacht_id', $entry->yacht_id)
            ->where('id', '!=', $entry->id ?? '')
            ->whereIn('status', ['pending', 'approved'])
            ->whereHas('regatta', function ($q) use ($regatta) {
                $q->where('date_start', '<=', $regatta->date_end)
                  ->where('date_end', '>=', $regatta->date_start);
            })
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'yachtId' => "Яхта «{$yacht->name}» уже занята в указанный период.",
            ]);
        }
    }

    /**
     * После одобрения заявки — проставляем submitted_at если не задан.
     */
    public function updated(RegattaEntry $entry): void
    {
        if ($entry->wasChanged('status') && $entry->status === 'approved') {
            $entry->updateQuietly(['submitted_at' => $entry->submitted_at ?? now()]);
        }
    }
}
