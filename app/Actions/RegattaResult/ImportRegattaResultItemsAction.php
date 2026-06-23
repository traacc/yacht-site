<?php

namespace App\Actions\RegattaResult;

use App\Models\RegattaResult;
use App\Models\RegattaResultItem;
use App\Models\Team;
use App\Models\Yacht;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Импортирует items из CSV в существующий RegattaResult.
 *
 * Формат CSV (заголовок обязателен):
 *   final_position,team_name,yacht_name,total_points
 *
 * - team_name  — ищется по точному совпадению (case-insensitive)
 * - yacht_name — необязательное поле; если пусто — NULL
 * - Дубликаты команды в рамках одного результата пропускаются
 */
class ImportRegattaResultItemsAction
{
    /**
     * @param  RegattaResult  $result
     * @param  string         $csvContent  Содержимое CSV-файла
     * @param  bool           $replace     Заменить существующие items (true) или добавить (false)
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function execute(RegattaResult $result, string $csvContent, bool $replace = false): array
    {
        $rows   = $this->parseCsv($csvContent);
        $errors = [];
        $items  = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // +1 заголовок, +1 нумерация с 1

            $teamName  = trim($row['team_name'] ?? '');
            $yachtName = trim($row['yacht_name'] ?? '');
            $points    = $row['total_points'] ?? null;
            $position  = $row['final_position'] ?? null;

            if ($teamName === '') {
                $errors[] = "Строка {$line}: пустое поле team_name";
                continue;
            }

            $team = Team::whereRaw('LOWER(name) = ?', [mb_strtolower($teamName)])->first();
            if ($team === null) {
                $errors[] = "Строка {$line}: команда «{$teamName}» не найдена";
                continue;
            }

            $yachtId = null;
            if ($yachtName !== '') {
                $yacht = Yacht::whereRaw('LOWER(name) = ?', [mb_strtolower($yachtName)])->first();
                if ($yacht === null) {
                    $errors[] = "Строка {$line}: яхта «{$yachtName}» не найдена";
                    continue;
                }
                $yachtId = $yacht->id;
            }

            if ($points === null || $points === '') {
                $errors[] = "Строка {$line}: пустое поле total_points";
                continue;
            }

            $items[] = [
                'team_id'       => $team->id,
                'yacht_id'      => $yachtId,
                // Запятую как десятичный разделитель приводим к точке (русская раскладка).
                'total_points'  => (float) str_replace(',', '.', trim((string) $points)),
                'final_position' => ($position !== null && $position !== '') ? $position : null,
                //'final_position' => ($position !== null && $position !== '') ? (int) $position : null,
            ];
        }

        $imported = 0;
        $skipped  = 0;

        DB::transaction(function () use ($result, $items, $replace, &$imported, &$skipped) {
            if ($replace) {
                $result->items()->delete();
            }

            $existingTeamIds = $replace
                ? collect()
                : $result->items()->pluck('team_id');

            foreach ($items as $item) {
                if ($existingTeamIds->contains($item['team_id'])) {
                    $skipped++;
                    continue;
                }

                RegattaResultItem::create([
                    'regatta_result_id' => $result->id,
                    ...$item,
                ]);

                $imported++;
            }
        });

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function parseCsv(string $content): Collection
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        if (count($lines) < 2) {
            throw new RuntimeException('CSV-файл пуст или содержит только заголовок.');
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $headers   = array_map('trim', str_getcsv(array_shift($lines), $delimiter));

        $required = ['team_name', 'total_points'];
        foreach ($required as $col) {
            if (! in_array($col, $headers, true)) {
                throw new RuntimeException("CSV-файл не содержит обязательного столбца «{$col}».");
            }
        }

        return collect($lines)
            ->filter(fn(string $line) => trim($line) !== '')
            ->values()
            ->map(function (string $line) use ($headers, $delimiter) {
                $values = str_getcsv($line, $delimiter);
                return array_combine(
                    $headers,
                    array_pad($values, count($headers), '')
                );
            });
    }

    private function detectDelimiter(string $headerLine): string
    {
        $counts = [
            ','  => substr_count($headerLine, ','),
            ';'  => substr_count($headerLine, ';'),
            "\t" => substr_count($headerLine, "\t"),
        ];
        arsort($counts);
        return array_key_first($counts);
    }
}
