<?php

declare(strict_types=1);

namespace App\Actions\RegattaResult;

use App\Enums\RegattaEntrySource;
use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\RaceResult;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaEvents;
use App\Models\RegattaResult;
use App\Models\RegattaResultItem;
use App\Models\Team;
use App\Models\Yacht;
use Illuminate\Support\Facades\DB;

/**
 * Записывает результаты одного зачётного класса в выбранный результат регаты:
 * итоговую таблицу (RegattaResultItem) и пооночные результаты (RaceResult).
 *
 * Общий движок записи для всех источников результата. На вход принимает уже
 * разобранную каноническую структуру $data (races + crews) — не важно, откуда
 * она получена: из судейского .rgd (через RgdParser, см. ImportRgdResultItemsAction)
 * или из JSON внешней программы (см. RegattaResultsController). Так вся логика
 * привязки и идемпотентности живёт в одном месте.
 *
 * Каноническая форма $data:
 *   [
 *     'races' => [['name' => string, 'at' => string], ...],
 *     'crews' => [[
 *       'final_position' => string, 'sail' => string, 'country' => string,
 *       'yacht' => string, 'type' => string, 'total_points' => string,
 *       'city' => string, 'team' => string,
 *       'races' => [['position' => string, 'points' => string], ...],
 *     ], ...],
 *   ]
 *
 * Якорь участника — ЯХТА (парусный номер vfps_number): заявка и строка итога
 * ищутся и создаются по yacht_id (в рамках регаты/результата), поэтому результаты
 * всегда привязаны к конкретной лодке, а не к имени клуба. $createMissing управляет
 * только созданием недостающих ЯХТ. Команда (обязательна по схеме) — заглушка на
 * яхту. Идемпотентно: повторный запуск обновляет записи той же яхты, не плодя дублей.
 */
class ApplyRegattaResultsAction
{
    /**
     * @param  array{races: array<int, array{name: string, at: string}>, crews: array<int, array<string, mixed>>}  $data
     * @return array{imported: int, skipped: int, errors: string[], created_yachts: int, created_teams: int}
     */
    public function execute(
        RegattaResult $result,
        array $data,
        bool $createMissing = false,
        bool $replace = false,
    ): array {
        $regatta = $result->regatta;
        $boatCount = count($data['crews']);

        $errors = [];
        $imported = 0;
        $skipped = 0;
        $createdYachts = 0;
        $createdTeams = 0;

        DB::transaction(function () use (
            $result, $regatta, $data, $createMissing, $replace, $boatCount,
            &$errors, &$imported, &$skipped, &$createdYachts, &$createdTeams,
        ): void {
            if ($replace) {
                // Не через связь items(): у неё orderByRaw с CAST(final_position AS UNSIGNED),
                // и MySQL в строгом режиме падает на нечисловых значениях (напр. '----')
                // при переносе ORDER BY в DELETE. Чистое удаление без сортировки.
                RegattaResultItem::where('regatta_result_id', $result->id)->delete();
            }

            // Гонки регаты — идемпотентно по имени; порядок сохраняем для RaceResult.
            $events = [];
            foreach ($data['races'] as $race) {
                $events[] = RegattaEvents::firstOrCreate(
                    ['regatta_id' => $regatta->id, 'name' => $race['name']],
                    ['event_datetime' => $race['at']],
                );
            }

            foreach ($data['crews'] as $crew) {
                $sail = trim((string) $crew['sail']);

                if ($sail === '') {
                    $errors[] = "Экипаж «{$crew['yacht']}»: пустой парусный номер — пропущен";
                    $skipped++;

                    continue;
                }

                // OwnedScope прячет безвладельческие яхты — ищем без него.
                $yacht = Yacht::withoutGlobalScopes()->where('vfps_number', $sail)->first();

                if ($yacht === null) {
                    if (! $createMissing) {
                        $errors[] = "Яхта с парусным № {$sail} («{$crew['yacht']}») не найдена — пропущена";
                        $skipped++;

                        continue;
                    }

                    $yacht = Yacht::withoutGlobalScopes()->create([
                        'vfps_number' => $sail,
                        'name' => $crew['yacht'],
                        'class' => 'Carter30',
                        'project' => $crew['type'] ?: null,
                        'reg_place' => $crew['city'] ?: null,
                        'approval_status' => 'approved',
                    ]);
                    $createdYachts++;
                }

                // Заявка привязана к ЯХТЕ: ищем по (регата, yacht_id) — так результаты
                // всегда относятся к нужной лодке, а не к первой заявке того же клуба.
                $entry = RegattaEntry::where('regatta_id', $regatta->id)
                    ->where('yacht_id', $yacht->id)
                    ->first();

                if ($entry === null) {
                    // Команда обязательна по схеме — заглушка на яхту (клуб или имя яхты).
                    $team = $this->ensureTeam($crew['team'], $yacht, $sail, $regatta, $createdTeams);
                    $entry = RegattaEntry::create([
                        'regatta_id' => $regatta->id,
                        'team_id' => $team->id,
                        'yacht_id' => $yacht->id,
                        'status' => 'approved',
                        'source' => RegattaEntrySource::Admin,
                        'submitted_at' => now(),
                    ]);
                }

                foreach ($crew['races'] as $i => $race) {
                    if (! isset($events[$i])) {
                        continue;
                    }

                    $points = RegattaResultResource::deriveRacePoints($race['position'], $race['points'], $boatCount);

                    RaceResult::updateOrCreate(
                        ['event_id' => $events[$i]->id, 'regatta_entry_id' => $entry->id],
                        ['position' => $race['position'], 'points' => (string) $points],
                    );
                }

                // Строка итога тоже привязана к яхте: ищем по (результат, yacht_id).
                // Итоговые очки/место — из официального протокола; overridden, чтобы
                // авторасчёт не менял судейские тай-брейки (равные очки → разные места).
                $item = RegattaResultItem::where('regatta_result_id', $result->id)
                    ->where('yacht_id', $yacht->id)
                    ->first();

                $item?->update([
                    'not_participate' => false,
                    'total_points' => $crew['total_points'],
                    'final_position' => $crew['final_position'],
                    'total_points_overridden' => true,
                    'final_position_overridden' => true,
                ]);

                if ($item === null) {
                    RegattaResultItem::create([
                        'regatta_result_id' => $result->id,
                        'team_id' => $entry->team_id,
                        'yacht_id' => $yacht->id,
                        'not_participate' => false,
                        'total_points' => $crew['total_points'],
                        'final_position' => $crew['final_position'],
                        'total_points_overridden' => true,
                        'final_position_overridden' => true,
                    ]);
                }

                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'created_yachts' => $createdYachts,
            'created_teams' => $createdTeams,
        ];
    }

    /**
     * Команда-заглушка для яхты (team_id обязателен по схеме, но при привязке по яхте
     * не несёт смысла). Имя — из «Команда/Спонсор» файла, иначе имя яхты, иначе номер.
     * Если такое имя уже занято ДРУГОЙ яхтой в этой регате (один клуб на несколько
     * лодок), дописываем парусный номер, чтобы team_id остался уникальным per-yacht.
     */
    private function ensureTeam(string $rawClub, Yacht $yacht, string $sail, Regatta $regatta, int &$createdTeams): Team
    {
        $base = trim($rawClub);
        if ($base === '') {
            $base = $yacht->name !== '' ? $yacht->name : "Яхта {$sail}";
        }

        foreach ([$base, "{$base} ({$sail})"] as $name) {
            $team = Team::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

            if ($team === null) {
                $createdTeams++;

                return Team::create(['name' => $name, 'approval_status' => 'approved']);
            }

            // Переиспользуем существующую команду, только если она не привязана к другой
            // яхте в этой регате (иначе нарушится unique(regatta_id, team_id)).
            $takenByOther = RegattaEntry::where('regatta_id', $regatta->id)
                ->where('team_id', $team->id)
                ->where('yacht_id', '!=', $yacht->id)
                ->exists();

            if (! $takenByOther) {
                return $team;
            }
        }

        // Оба варианта заняты другими яхтами — крайне маловероятно; создаём с номером.
        $createdTeams++;

        return Team::create(['name' => "{$base} · {$sail}", 'approval_status' => 'approved']);
    }
}
