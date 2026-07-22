<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParticipantResource;
use App\Models\Regatta;
use App\Models\RegattaEvents;
use App\Services\RgdParticipantsExporter;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/regattas/{regatta}/participants
 *
 * Экспорт участников регаты для внешней (судейской) программы в JSON.
 * Регата резолвится по external_id. Зачётная группа — «КАРТЕР 30»
 * (RgdParticipantsExporter::CLASS_NAME), как и в файловом экспорте .rgd.
 */
class RegattaParticipantsController extends Controller
{
    public function __construct(private readonly RgdParticipantsExporter $exporter) {}

    public function __invoke(Regatta $regatta): JsonResponse
    {
        $entries = $this->exporter->loadParticipants($regatta);

        return response()->json([
            'regatta' => [
                'external_id' => $regatta->external_id,
                'name' => $regatta->name,
                'water_area' => $regatta->water_area,
                'date_start' => $regatta->date_start?->format('Y-m-d'),
                'date_end' => $regatta->date_end?->format('Y-m-d'),
                'level_coefficient' => $regatta->level_coefficient !== null ? (float) $regatta->level_coefficient : null,
            ],
            'class' => RgdParticipantsExporter::CLASS_NAME,
            // Гонки регаты по порядку (по времени старта) — тот же формат, что
            // ожидает импорт результатов (races[].name / races[].at).
            'races' => $regatta->races->map(fn (RegattaEvents $race) => [
                'name' => $race->name,
                'at' => $race->event_datetime?->format('Y-m-d H:i:s'),
            ])->values(),
            'participants' => ParticipantResource::collection($entries),
        ]);
    }
}
