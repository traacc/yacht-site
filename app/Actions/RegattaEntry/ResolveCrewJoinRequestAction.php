<?php

declare(strict_types=1);

namespace App\Actions\RegattaEntry;

use App\Enums\CrewJoinRequestStatus;
use App\Models\CrewJoinRequest;
use App\Models\RegattaEntryCrew;
use App\Models\User;
use App\Notifications\CrewJoinRequestResolvedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Решение по отклику «Хочу в этот экипаж»: принять или отклонить.
 *
 * Принятый человек добавляется прямо в экипаж заявки, а не в команду: состав
 * команды — дело капитана, а участие в одной регате не должно менять её ростер.
 * Строка экипажа поэтому ссылается на пользователя (или хранит контакты гостя),
 * а не на `team_members` (@see миграция add_person_fields_to_regatta_entry_crew).
 */
final class ResolveCrewJoinRequestAction
{
    /**
     * @throws ValidationException
     */
    public function accept(CrewJoinRequest $request, User $actor, ?string $note = null): CrewJoinRequest
    {
        $request->loadMissing(['regattaEntry.regatta', 'regattaEntry.crew']);
        $entry = $request->regattaEntry;

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Решение по этому отклику уже принято.',
            ]);
        }

        $limit = $entry->regatta?->maxCrewSize();

        if ($limit !== null && $entry->crew()->count() >= $limit) {
            throw ValidationException::withMessages([
                'crew' => "Экипаж уже укомплектован: максимум {$limit} чел.",
            ]);
        }

        DB::transaction(function () use ($request, $entry, $actor, $note): void {
            RegattaEntryCrew::create([
                'regatta_entry_id' => $entry->id,
                'user_id' => $request->user_id,
                'full_name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'role' => 'main',
            ]);

            $request->update([
                'status' => CrewJoinRequestStatus::Accepted,
                'response_note' => $note,
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
            ]);
        });

        $this->notifyApplicant($request->refresh(), accepted: true);

        return $request;
    }

    /**
     * @throws ValidationException
     */
    public function decline(CrewJoinRequest $request, User $actor, ?string $note = null): CrewJoinRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Решение по этому отклику уже принято.',
            ]);
        }

        $request->update([
            'status' => CrewJoinRequestStatus::Declined,
            'response_note' => $note,
            'resolved_at' => now(),
            'resolved_by' => $actor->id,
        ]);

        $this->notifyApplicant($request->refresh(), accepted: false);

        return $request;
    }

    /** Гость аккаунта не имеет — с ним экипаж связывается по оставленным контактам. */
    private function notifyApplicant(CrewJoinRequest $request, bool $accepted): void
    {
        $request->loadMissing(['user', 'regattaEntry.regatta', 'regattaEntry.team']);

        if ($request->user === null) {
            return;
        }

        $entry = $request->regattaEntry;

        $request->user->notify(new CrewJoinRequestResolvedNotification(
            accepted: $accepted,
            regattaName: (string) $entry->regatta?->name,
            teamName: $entry->team?->name,
            responseNote: $request->response_note,
            regattaUrl: $entry->regatta ? route('competition-details', $entry->regatta) : null,
        ));
    }
}
