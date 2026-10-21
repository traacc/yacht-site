<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ParticipantSort;
use App\Models\Regatta;
use App\Models\RegattaEntryCrew;
use Illuminate\Support\Carbon;

/**
 * Плоский список участников регаты для админки: экипажи одобренных заявок,
 * без повторов. Участник сборного экипажа без аккаунта попадает по имени из
 * заявки, без даты рождения.
 */
class RegattaParticipants
{
    /**
     * @return array<int, array{name:string, birth_date:?Carbon, team:?string}>
     */
    public function forRegatta(Regatta $regatta, ParticipantSort $sort = ParticipantSort::Name): array
    {
        $entries = $regatta->entries()
            ->where('status', 'approved')
            ->with(['team:id,name', 'crew.teamMember.user:id,name,birth_date', 'crew.user:id,name,birth_date'])
            ->get();

        $rows = [];
        foreach ($entries as $entry) {
            foreach ($entry->crew as $crewMember) {
                /** @var RegattaEntryCrew $crewMember */
                $user = $crewMember->teamMember?->user ?? $crewMember->user;
                $key = $user?->id ?? 'name:'.mb_strtolower(trim((string) $crewMember->full_name));

                if ($user === null && blank($crewMember->full_name)) {
                    continue;
                }

                $rows[$key] ??= [
                    'name' => $user?->name ?? trim($crewMember->full_name),
                    'birth_date' => $user?->birth_date,
                    'team' => filled($entry->team?->name) ? trim($entry->team->name) : null,
                ];
            }
        }

        return $sort->apply(array_values($rows));
    }
}
