<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\RegattaResult\ApplyRegattaResultsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportResultsRequest;
use App\Models\Regatta;
use App\Models\RegattaResult;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/regattas/{regatta}/results
 *
 * Импорт результатов регаты от внешней (судейской) программы в JSON.
 * Регата резолвится по external_id. Запрос приводится к канонической структуре
 * (races + crews) и передаётся общему движку ApplyRegattaResultsAction — тому же,
 * что используется при импорте из файла .rgd.
 *
 * Результат регаты (RegattaResult) выбирается/создаётся по (регата, result_type,
 * source=imported). Привязка участников — по парусному номеру (vfps_number),
 * идемпотентно: повторный вызов обновляет строки той же яхты, не плодя дублей.
 */
class RegattaResultsController extends Controller
{
    public function __construct(private readonly ApplyRegattaResultsAction $apply) {}

    public function __invoke(ImportResultsRequest $request, Regatta $regatta): JsonResponse
    {
        $type = $request->input('result_type', 'preliminary');

        $result = RegattaResult::firstOrCreate(
            ['regatta_id' => $regatta->id, 'result_type' => $type, 'source' => 'imported'],
            ['is_published' => false],
        );

        $summary = $this->apply->execute(
            $result,
            $this->toCanonical($request),
            (bool) $request->boolean('create_missing'),
            (bool) $request->boolean('replace'),
        );

        return response()->json([
            'result_id' => $result->id,
            'result_type' => $result->result_type,
            'summary' => $summary,
        ], 200);
    }

    /**
     * Приводит JSON-запрос к канонической структуре ApplyRegattaResultsAction.
     *
     * @return array{races: array<int, array{name: string, at: string}>, crews: array<int, array<string, mixed>>}
     */
    private function toCanonical(ImportResultsRequest $request): array
    {
        $races = array_map(fn (array $r) => [
            'name' => (string) $r['name'],
            'at' => (string) ($r['at'] ?? ''),
        ], $request->input('races', []));

        $crews = array_map(function (array $c): array {
            $sail = trim((string) ($c['sail_number'] ?? ''));
            $yacht = trim((string) ($c['yacht_name'] ?? ''));

            return [
                'final_position' => (string) ($c['final_position'] ?? ''),
                'sail' => $sail,
                'country' => (string) ($c['country'] ?? 'RUS'),
                'yacht' => $yacht !== '' ? $yacht : "Яхта {$sail}",
                'type' => (string) ($c['type'] ?? ''),
                'total_points' => (string) ($c['total_points'] ?? ''),
                'city' => (string) ($c['city'] ?? ''),
                'team' => (string) ($c['team'] ?? ''),
                'races' => array_map(fn (array $r) => [
                    'position' => (string) ($r['position'] ?? ''),
                    'points' => (string) ($r['points'] ?? ''),
                ], $c['races'] ?? []),
            ];
        }, $request->input('crews', []));

        return ['races' => $races, 'crews' => $crews];
    }
}
