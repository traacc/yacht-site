<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Enums\TeamMemberRole;
use App\Models\RegattaEntry;
use App\Models\RegattaEntryCrew;
use App\Models\TeamMember;
use App\Models\User;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

/**
 * Общая логика селекта «Участник» в репитере экипажа заявки.
 *
 * Экипаж хранится через членство в команде (regatta_entry_crew.team_member_id),
 * поэтому пользователь без активного членства в списке опций отсутствует.
 * Такого пользователя можно найти поиском — он получает синтетическое значение
 * `user:<uuid>`, а членство создаётся при сохранении заявки в её команде
 * (так же, как это делают публичные формы подачи и правки заявки).
 */
trait ResolvesCrewMembers
{
    /** Префикс значения опции для пользователя без подходящего членства. */
    protected const CREW_USER_OPTION_PREFIX = 'user:';

    /**
     * Опции селекта «Участник» — активные члены команд.
     *
     * Админка выбирает из всех команд в рамках регаты (исключая тех, кто уже
     * заявлен в экипаже другой заявки на неё), кабинет — из своей команды.
     *
     * @return array<string, string>
     */
    public static function crewMemberOptions(?string $regattaId = null, ?string $teamId = null, ?RegattaEntry $record = null): array
    {
        if (blank($regattaId) && blank($teamId)) {
            return [];
        }

        return TeamMember::query()
            ->where('status', 'active')
            ->when($teamId, fn (Builder $query) => $query->where('team_id', $teamId))
            ->whereNotIn('id', static::crewTakenMemberIds($regattaId, $record))
            ->with('user')
            ->get()
            ->mapWithKeys(fn (TeamMember $member): array => [
                $member->id => $member->user?->name ?? 'Неизвестный',
            ])
            ->sort(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->all();
    }

    /**
     * Результаты поиска по селекту «Участник»: члены команд плюс пользователи
     * без активного членства — членство им создастся при сохранении заявки.
     *
     * @return array<string, string>
     */
    public static function crewMemberSearchResults(string $search, ?string $regattaId = null, ?string $teamId = null, ?RegattaEntry $record = null): array
    {
        return static::filterCrewOptions(static::crewMemberOptions($regattaId, $teamId, $record), $search)
            + static::crewUserSearchOptions($search, $teamId, static::crewTakenUserIds($regattaId, $record));
    }

    /**
     * Синхронизирует экипаж заявки с данными репитера.
     *
     * Сопоставление идёт по id строки экипажа, а не по членству в команде:
     * у участников сборного экипажа (индивидуальные и «местовые» заявки,
     * принятые отклики «Хочу в этот экипаж») `team_member_id` пуст, и по нему
     * такие строки не только не сохранялись бы, но и вычищались при первом же
     * сохранении заявки из админки.
     *
     * @param  array<int, array{id?: ?int, team_member_id?: ?string, full_name?: ?string, role?: ?string}>  $crew
     */
    public static function syncCrew(RegattaEntry $record, array $crew): void
    {
        $keptIds = [];

        foreach ($crew as $item) {
            $role = $item['role'] ?? 'main';

            // Значения вида `user:<uuid>` превращаются здесь в реальные членства.
            $memberId = static::resolveCrewTeamMemberId($record, $item['team_member_id'] ?? null);

            if ($memberId !== null) {
                $keptIds[] = $record->crew()->updateOrCreate(
                    ['team_member_id' => $memberId],
                    ['role' => $role],
                )->getKey();

                continue;
            }

            // Участник без членства: правим существующую строку, новую здесь не
            // создаём — человека со стороны добавляет действие по отклику.
            $row = filled($item['id'] ?? null)
                ? $record->crew()->whereKey($item['id'])->first()
                : null;

            if ($row === null) {
                continue;
            }

            $row->update([
                'role' => $role,
                'full_name' => $item['full_name'] ?? $row->full_name,
            ]);

            $keptIds[] = $row->getKey();
        }

        // Удаляем строки, которых нет в новом наборе (пустой набор — весь экипаж).
        $record->crew()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * Строка репитера описывает участника без членства в команде.
     *
     * Такие строки приходят из сборных экипажей и принятых откликов «Хочу в
     * этот экипаж»: выбирать в селекте нечего, имя показывается как есть.
     * Только что добавленная строка (без `id`) таковой не считается — в ней
     * админ как раз выбирает участника из команд.
     */
    public static function isCrewGuestRow(Get $get): bool
    {
        return filled($get('id')) && blank($get('team_member_id'));
    }

    /**
     * Строка экипажа в формате репитера формы.
     *
     * @return array{id: int, team_member_id: ?string, member_name: string, full_name: ?string, is_captain: bool, role: string}
     */
    public static function crewRowData(RegattaEntryCrew $crew): array
    {
        return [
            'id' => $crew->getKey(),
            'team_member_id' => $crew->team_member_id,
            // displayName() покрывает и участников команды, и сборный экипаж.
            'member_name' => $crew->displayName(),
            'full_name' => $crew->full_name,
            'is_captain' => $crew->role === 'captain',
            'role' => $crew->role,
        ];
    }

    /**
     * Подпись выбранной опции: и для членства, и для пользователя из поиска.
     */
    public static function crewMemberOptionLabel(?string $value): ?string
    {
        $name = static::crewMemberName($value);

        if ($name === '') {
            return null;
        }

        return static::isCrewUserOption($value) ? static::labelForUserWithoutTeam($name) : $name;
    }

    /**
     * Имя участника по значению селекта — без пометок, для скрытого member_name.
     */
    public static function crewMemberName(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        if (static::isCrewUserOption($value)) {
            return User::find(static::crewOptionUserId($value))?->name ?? '';
        }

        return TeamMember::with('user')->find($value)?->user?->name ?? '';
    }

    /**
     * Пользователи без активного членства, подходящие под поисковый запрос.
     *
     * @param  ?string  $teamId  учитывать членство только в этой команде (null — в любой)
     * @param  array<int, string>  $excludedUserIds
     * @return array<string, string>
     */
    protected static function crewUserSearchOptions(string $search, ?string $teamId = null, array $excludedUserIds = []): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        return User::query()
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->whereDoesntHave('teamMemberships', function (Builder $query) use ($teamId): void {
                $query->where('status', 'active')
                    ->when($teamId, fn (Builder $q) => $q->where('team_id', $teamId));
            })
            ->when($excludedUserIds !== [], fn (Builder $query) => $query->whereNotIn('id', $excludedUserIds))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user): array => [
                static::CREW_USER_OPTION_PREFIX.$user->id => static::labelForUserWithoutTeam($user->name),
            ])
            ->all();
    }

    /**
     * Членства, уже занятые экипажами других заявок на эту регату.
     *
     * @return array<int, string>
     */
    protected static function crewTakenMemberIds(?string $regattaId, ?RegattaEntry $record = null): array
    {
        if (blank($regattaId)) {
            return [];
        }

        return RegattaEntryCrew::query()
            ->whereHas('regattaEntry', function (Builder $query) use ($regattaId, $record): void {
                $query->where('regatta_id', $regattaId);

                if ($record) {
                    $query->whereKeyNot($record->getKey());
                }
            })
            ->pluck('team_member_id')
            ->all();
    }

    /**
     * Пользователи, уже заявленные в экипажах других заявок на эту регату.
     *
     * @return array<int, string>
     */
    protected static function crewTakenUserIds(?string $regattaId, ?RegattaEntry $record = null): array
    {
        $memberIds = static::crewTakenMemberIds($regattaId, $record);

        if ($memberIds === []) {
            return [];
        }

        return TeamMember::query()
            ->whereIn('id', $memberIds)
            ->pluck('user_id')
            ->all();
    }

    /**
     * Отбирает из готового списка опций подходящие под поисковый запрос.
     *
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    protected static function filterCrewOptions(array $options, string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return $options;
        }

        return array_filter(
            $options,
            fn (string $label): bool => mb_stripos($label, $search) !== false,
        );
    }

    /**
     * Приводит значение селекта к id членства, создавая членство в команде
     * заявки для пользователя, выбранного поиском.
     */
    protected static function resolveCrewTeamMemberId(RegattaEntry $record, ?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (! static::isCrewUserOption($value)) {
            return $value;
        }

        $userId = static::crewOptionUserId($value);

        if (blank($record->team_id) || ! User::whereKey($userId)->exists()) {
            return null;
        }

        $member = TeamMember::firstOrCreate(
            [
                'team_id' => $record->team_id,
                'user_id' => $userId,
            ],
            [
                'role' => TeamMemberRole::Member->value,
                'status' => 'active',
                'joined_at' => now(),
            ],
        );

        // Приглашение/выход: участник заявлен в экипаж — членство снова активно.
        if ($member->status !== 'active') {
            $member->update([
                'status' => 'active',
                'joined_at' => $member->joined_at ?? now(),
            ]);
        }

        return (string) $member->id;
    }

    protected static function isCrewUserOption(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, static::CREW_USER_OPTION_PREFIX);
    }

    protected static function crewOptionUserId(string $value): string
    {
        return substr($value, strlen(static::CREW_USER_OPTION_PREFIX));
    }

    protected static function labelForUserWithoutTeam(string $name): string
    {
        return $name.' — не в команде';
    }
}
