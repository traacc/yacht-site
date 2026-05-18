<?php

declare(strict_types=1);

namespace App\Actions\Regatta;

use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Team;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Validation\ValidationException;

class SubmitRegattaEntryAction
{
    /**
     * Подать заявку команды на участие в регате.
     *
     * @throws ValidationException
     */
    public function handle(Regatta $regatta, Team $team, Yacht $yacht, User $user): RegattaEntry
    {
        // Проверяем, что пользователь является организатором команды
        $isOrganizer = $team->teamMembers()
            ->where('user_id', $user->id)
            ->where('role', 'organizer')
            ->where('status', 'active')
            ->exists();

        if (! $isOrganizer) {
            throw ValidationException::withMessages([
                'teamId' => 'Вы не являетесь организатором этой команды.',
            ]);
        }

        // Проверяем, что яхта принадлежит пользователю
        if ($yacht->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'yachtId' => 'Выбранная яхта не принадлежит вам.',
            ]);
        }

        // Проверяем, не подана ли уже заявка от этой команды
        if ($regatta->hasTeam($team)) {
            throw ValidationException::withMessages([
                'teamId' => 'Заявка от этой команды уже подана.',
            ]);
        }

        // Проверяем занятость яхты в период регаты
        if ($yacht->isBusyDuring(
            $regatta->date_start->format('Y-m-d'),
            $regatta->date_end->format('Y-m-d')
        )) {
            throw ValidationException::withMessages([
                'yachtId' => 'Выбранная яхта уже занята в этот период.',
            ]);
        }

        return RegattaEntry::create([
            'regatta_id' => $regatta->id,
            'team_id'    => $team->id,
            'yacht_id'   => $yacht->id,
            'status'     => 'pending',
            'submitted_at' => now(),
        ]);
    }
}
