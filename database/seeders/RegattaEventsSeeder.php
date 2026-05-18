<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Regatta;
use App\Models\RegattaEvents;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegattaEventsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the regatta events (races and schedule items).
     */
    public function run(): void
    {
        $regattas = Regatta::all(['id', 'name', 'date_start', 'races_count', 'race_days_count']);

        if ($regattas->isEmpty()) {
            return;
        }

        foreach ($regattas as $regatta) {
            // --- Schedule events (non-race) ---
            $scheduleEvents = [
                ['name' => 'Регистрация участников', 'event_number' => 0, 'time' => '08:00'],
                ['name' => 'Открытие регаты',        'event_number' => 1, 'time' => '09:30'],
                ['name' => 'Брифинг для рулевых',    'event_number' => 2, 'time' => '10:00'],
            ];

            foreach ($scheduleEvents as $i => $event) {
                RegattaEvents::factory()
                    ->schedule()
                    ->forRegatta($regatta)
                    ->eventNumber($event['event_number'])
                    ->create([
                        'name'        => $event['name'],
                        'description' => $i === 0
                            ? 'Приём и регистрация экипажей, проверка документов.'
                            : ($i === 1
                                ? 'Торжественная церемония открытия соревнований.'
                                : 'Обсуждение условий гонок, погоды и дистанций.'),
                        'event_datetime' => $regatta->date_start
                        ->copy()
                        ->setTimeFromTimeString($event['time']),
                    ]);
            }

            // --- Race events ---
            $raceCount = $regatta->races_count ?? 3;

            for ($raceNum = 1; $raceNum <= $raceCount; $raceNum++) {
                $raceDayOffset = (int) ceil($raceNum / max($regatta->race_days_count ?? 1, 1));

                RegattaEvents::factory()
                    ->race()
                    ->forRegatta($regatta)
                    ->eventNumber($raceNum + 2) // after schedule events
                    ->create([
                        'name'        => "Гонка №{$raceNum}",
                        'description' => "Гоночный заезд №{$raceNum} регаты «{$regatta->name}».",
                        'event_datetime'  => $regatta->date_start?->copy()->addDays($raceDayOffset)->copy()
                        ->addDays($raceDayOffset)
                        ->setTimeFromTimeString('11:00')
                        ->addHours($raceNum * 2),
                    ]);
            }
        }
    }
}