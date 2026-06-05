<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\TeamMemberRole;
use App\Models\TeamMember;

/**
 * Синхронизирует team.organizer_id при изменении ролей в teamMembers.
 */
final class TeamMemberObserver
{
    /**
     * После создания/обновления: если роль — organizer, проставляем organizer_id.
     * Если роль была organizer и сменилась — ищем другого organizer или сбрасываем.
     */
    public function saved(TeamMember $teamMember): void
    {
        if ($teamMember->role === TeamMemberRole::Organizer->value) {
            $teamMember->team()->update(['organizer_id' => $teamMember->user_id]);
            return;
        }

        // Роль была organizer, но сменилась на другую
        if ($teamMember->wasChanged('role') && $teamMember->getOriginal('role') === TeamMemberRole::Organizer->value) {
            $this->syncOrganizerFromMembers($teamMember);
        }
    }

    /**
     * После удаления: если удалён organizer — ищем другого или сбрасываем.
     */
    public function deleted(TeamMember $teamMember): void
    {
        if ($teamMember->role === TeamMemberRole::Organizer->value) {
            $this->syncOrganizerFromMembers($teamMember);
        }
    }

    /**
     * Найти другого organizer среди активных участников команды
     * и обновить organizer_id. Если organizer нет — сбросить в null.
     */
    private function syncOrganizerFromMembers(TeamMember $teamMember): void
    {
        $newOrganizer = $teamMember->team->teamMembers()
            ->where('role', TeamMemberRole::Organizer->value)
            ->where('status', 'active')
            ->first();

        $teamMember->team()->update([
            'organizer_id' => $newOrganizer?->user_id,
        ]);
    }
}
