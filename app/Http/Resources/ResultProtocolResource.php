<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RegattaResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Протокол результата регаты (RegattaResult) для JSON-API: тип/источник/публикация.
 * У регаты может быть несколько протоколов (предварительный/итоговый).
 *
 * @mixin RegattaResult
 */
class ResultProtocolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'result_id' => $this->id,
            'result_type' => $this->result_type,   // preliminary | final
            'source' => $this->source,         // imported | manual | ...
            'is_published' => (bool) $this->is_published,
        ];
    }
}
