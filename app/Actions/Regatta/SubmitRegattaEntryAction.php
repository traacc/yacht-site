<?php

declare(strict_types=1);

namespace App\Actions\Regatta;

use App\Enums\PaymentStatus;
use App\Enums\RegattaEntrySource;
use App\Enums\TeamMemberRole;
use App\Exceptions\InsufficientTeamRoleException;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Yacht;
use App\Services\TeamRoleGuard;
use Illuminate\Validation\ValidationException;

final class SubmitRegattaEntryAction
{
    /**
     * Подать заявку команды на участие в регате.
     * Требует роль Organizer или TeamAdmin у вызывающего пользователя.
     *
     * @param array<string, 'main'|'reserve'|'captain'> $crew  team_member_id => role
     *
     * @throws InsufficientTeamRoleException
     * @throws ValidationException
     */
    public function handle(Regatta $regatta, Team $team, Yacht $yacht, User $actor, array $crew = [], ?string $entryPassword = null, bool $feePaid = false): RegattaEntry
    {
        TeamRoleGuard::authorize($team, $actor, TeamMemberRole::ACTION_SUBMIT_ENTRY);

        // Проверяем, что яхта принадлежит пользователю
        /*
        if ($yacht->user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'yachtId' => 'Выбранная яхта не принадлежит вам.',
            ]);
        }
        */
        
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

        $entry = RegattaEntry::create([
            'regatta_id'     => $regatta->id,
            'team_id'        => $team->id,
            'yacht_id'       => $yacht->id,
            'status'         => 'approved',
            'source'         => RegattaEntrySource::QuickRequest,
            'submitted_at'   => now(),
            // Сбор за участие отмечается участником только если регата его требует
            'fee_paid'       => $regatta->entry_fee_required ? $feePaid : false,
            // Хешируется кастом 'hashed' в модели
            'entry_password' => $entryPassword ?: null,
        ]);

        // Если регата требует сбор — создаём запись в реестре платежей,
        // привязанную к заявке (полиморфная связь payable).
        if ($regatta->entry_fee_required) {
            $entry->paymentRegistries()->create([
                'name'   => "Сбор за участие — {$regatta->name} ({$team->name})",
                'amount' => $regatta->entry_fee_amount,
                'status' => $feePaid ? PaymentStatus::Paid : PaymentStatus::Pending,
            ]);
        }

        // Сохраняем экипаж
        if ($crew !== []) {
            // Валидируем, что все team_member_id принадлежат этой команде и активны
            $validMemberIds = $team->members()
                ->wherePivot('status', 'active')
                ->pluck('team_members.id')
                ->toArray();

            // Проверяем, что captain только один
            $captainCount = count(array_filter($crew, fn (string $role): bool => $role === 'captain'));
            if ($captainCount > 1) {
                throw ValidationException::withMessages([
                    'crew' => 'В экипаже может быть только один Рулевой.',
                ]);
            }

            foreach ($crew as $memberId => $role) {
                // Пропускаем участников без выбранной роли
                if ($role === '' || $role === null) {
                    continue;
                }

                if (! in_array($memberId, $validMemberIds, true)) {
                    throw ValidationException::withMessages([
                        'crew' => "Участник {$memberId} не состоит в команде или не активен.",
                    ]);
                }

                $entry->crew()->create([
                    'team_member_id' => $memberId,
                    'role'           => $role,
                ]);
            }
        }

        return $entry;
    }
}
