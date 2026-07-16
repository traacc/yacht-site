<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RegattaResultItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Строка итоговой таблицы результата регаты для JSON-API. Имена команды/яхты/номера
 * берутся из живой связи, а при удалении — из денормализованного снапшота
 * (display-аксессоры), поэтому результат уцелевает даже после удаления команды/яхты.
 *
 * @mixin RegattaResultItem
 */
class ResultItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'final_position' => $this->final_position,
            'total_points' => $this->total_points,
            'not_participate' => (bool) $this->not_participate,
            'sail_number' => $this->display_sail_number,
            'yacht_name' => $this->display_yacht_name,
            'team_name' => $this->display_team_name,
            'captain_name' => $this->captain_name,
            // Пооночная разбивка — снапшот протокола (может отсутствовать).
            'race_breakdown' => $this->race_breakdown,
        ];
    }
}
