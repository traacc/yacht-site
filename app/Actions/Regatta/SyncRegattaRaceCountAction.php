<?php

declare(strict_types=1);

namespace App\Actions\Regatta;

use App\Models\RaceResult;
use App\Models\Regatta;
use App\Models\RegattaEvents;
use Illuminate\Support\Collection;

/**
 * Приводит число гонок регаты (RegattaEvents) к заданному.
 *
 * Недостающие гонки создаются с числовыми именами по порядку («1», «2», …) —
 * секретарю не нужно придумывать названия, а переименовать их можно отдельно.
 * Лишние удаляются с конца, но только те, по которым ещё нет результатов:
 * удаление гонки с результатами обнулило бы уже введённый протокол.
 */
class SyncRegattaRaceCountAction
{
    /**
     * @return array{created: int, deleted: int, kept: array<int, string>}
     *                                                                     kept — имена гонок, которые не удалось удалить из-за результатов
     */
    public function execute(Regatta $regatta, int $count): array
    {
        $count = max(0, $count);

        $races = self::orderedRaces($regatta->id);
        $created = 0;
        $deleted = 0;
        $kept = [];

        if ($races->count() < $count) {
            $taken = $races->pluck('name')->map(fn (?string $name): string => trim((string) $name))->all();

            for ($i = $races->count(); $i < $count; $i++) {
                // Номер гонки — первый свободный: имена существующих гонок могли
                // быть изменены вручную, и повтор имени сломал бы порядок колонок.
                $number = $i + 1;
                while (in_array((string) $number, $taken, true)) {
                    $number++;
                }

                RegattaEvents::create([
                    'regatta_id' => $regatta->id,
                    'name' => (string) $number,
                ]);

                $taken[] = (string) $number;
                $created++;
            }

            return ['created' => $created, 'deleted' => $deleted, 'kept' => $kept];
        }

        // Удаляем с конца: гонки с результатами оставляем и сообщаем о них.
        foreach ($races->reverse() as $race) {
            if ($races->count() - $deleted <= $count) {
                break;
            }

            if (RaceResult::where('event_id', $race->id)->exists()) {
                $kept[] = (string) $race->name;

                continue;
            }

            $race->delete();
            $deleted++;
        }

        return ['created' => $created, 'deleted' => $deleted, 'kept' => $kept];
    }

    /**
     * Гонки регаты в том же порядке, в каком их показывает таблица результатов.
     *
     * @return Collection<int, RegattaEvents>
     */
    public static function orderedRaces(string $regattaId): Collection
    {
        return RegattaEvents::query()
            ->where('regatta_id', $regattaId)
            // Гонки без даты — в конец: MySQL по умолчанию ставит NULL первыми,
            // и тогда только что созданные по количеству гонки вставали бы перед
            // уже проведёнными датированными, сдвигая колонки таблицы.
            ->orderByRaw('event_datetime IS NULL')
            ->orderBy('event_datetime')
            // Имена гонок по умолчанию числовые, поэтому сортируем как числа:
            // строковый порядок дал бы «1, 10, 2». Нечисловые имена дают 0
            // и упорядочиваются по алфавиту вторым ключом.
            ->orderByRaw('CAST(name AS UNSIGNED)')
            ->orderBy('name')
            ->get()
            ->values();
    }
}
