<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\RegattaStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RegattaResource;
use App\Models\Regatta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/regattas
 *
 * Список регат для внешней (судейской) программы — чтобы найти external_id нужной
 * регаты для эндпоинтов участников и результатов. Опциональный фильтр ?status=,
 * сортировка по дате старта (свежие первыми).
 */
class RegattaListController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $regattas = Regatta::query()
            ->withCount('entries')
            ->when(
                $request->filled('status') && RegattaStatus::tryFrom($request->string('status')->value()),
                fn ($q) => $q->where('regatta_status', $request->string('status')->value()),
            )
            ->orderByDesc('date_start')
            ->get();

        return RegattaResource::collection($regattas);
    }
}
