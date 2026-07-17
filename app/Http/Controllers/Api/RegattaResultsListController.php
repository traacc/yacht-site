<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResultProtocolResource;
use App\Models\Regatta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/regattas/{regatta}/results
 *
 * Список протоколов результатов регаты (предварительный/итоговый) с итоговыми
 * таблицами для внешней (судейской) программы. Регата резолвится по external_id.
 * Опциональные фильтры: ?type=preliminary|final, ?published=1.
 */
class RegattaResultsListController extends Controller
{
    public function __invoke(Request $request, Regatta $regatta): JsonResponse
    {
        $results = $regatta->results()
            ->when(
                in_array($request->string('type')->value(), ['preliminary', 'final'], true),
                fn ($q) => $q->where('result_type', $request->string('type')->value()),
            )
            ->when(
                $request->has('published'),
                fn ($q) => $q->where('is_published', $request->boolean('published')),
            )
            ->get();

        return response()->json([
            'regatta' => [
                'external_id' => $regatta->external_id,
                'name' => $regatta->name,
            ],
            'results' => ResultProtocolResource::collection($results),
        ]);
    }
}
