<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\RegattaStatus;
use App\Models\Regatta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Регата для JSON-API внешней программы (список регат). Публичный идентификатор —
 * external_id (стабильный целочисленный номер), по нему строятся пути участников
 * и результатов. entries_count отдаётся, если загружен через withCount('entries').
 *
 * @mixin Regatta
 */
class RegattaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->regatta_status;

        return [
            'external_id' => $this->external_id,
            'name' => $this->name,
            'water_area' => $this->water_area,
            'location' => $this->location,
            'date_start' => $this->date_start?->format('Y-m-d'),
            'date_end' => $this->date_end?->format('Y-m-d'),
            'status' => $status instanceof RegattaStatus ? $status->value : $status,
            'entries_count' => $this->whenCounted('entries'),
        ];
    }
}
