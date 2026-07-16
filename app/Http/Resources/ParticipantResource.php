<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\SportCategory;
use App\Models\RegattaEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Участник регаты для JSON-API внешней программы. Поля соответствуют строке
 * участника судейского .rgd (см. RgdParticipantsExporter::participantRow):
 * парусный номер как якорь, яхта, команда и состав экипажа с ролями.
 *
 * @mixin RegattaEntry
 */
class ParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $yacht = $this->yacht;

        return [
            'entry_id' => $this->id,
            'country' => 'RUS',                 // в БД не хранится
            'sail_number' => $yacht?->vfps_number,
            'yacht' => [
                'name' => $yacht?->name,
                'class' => $yacht?->class,
                'type' => $yacht?->project,        // тип/проект
                'city' => $yacht?->reg_place,
            ],
            'team' => $this->team?->name,
            'crew' => $this->crew
                ->map(fn ($member) => $this->crewMember($member))
                ->filter(fn (array $m) => $m['name'] !== null)
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crewMember(mixed $member): array
    {
        $user = $member->teamMember?->user;
        $category = $user?->sport_category;

        return [
            'name' => filled($user?->name) ? trim((string) $user->name) : null,
            'birth_date' => $user?->birth_date?->format('Y-m-d'),
            'sport_category' => $category instanceof SportCategory ? $category->value : $category,
            'role' => $member->role,
        ];
    }
}
