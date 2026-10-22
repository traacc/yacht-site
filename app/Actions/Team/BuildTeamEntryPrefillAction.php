<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaEntryCrew;
use App\Models\Scopes\OwnedScope;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Yacht;

/**
 * Автозаполнение быстрой заявки по выбранной команде.
 *
 * Источник — последняя действующая заявка команды на другую регату:
 *  — яхта: яхта команды по умолчанию, иначе яхта из последней заявки
 *    (только если она ещё не заявлена на текущую регату);
 *  — рулевой: рулевой последней заявки, если он всё ещё активен в команде;
 *  — экипаж: активные участники команды в порядке «экипаж прошлой регаты →
 *    постоянные участники → остальные». Отсечение по числу мест делает форма.
 *
 * Участники, уже заявленные на текущую регату в другом экипаже, не предлагаются.
 */
final class BuildTeamEntryPrefillAction
{
    /** Статусы заявок, которые не занимают людей и яхты. */
    private const INACTIVE_STATUSES = ['withdrawn', 'rejected'];

    /**
     * @param  ?string  $fallbackCaptainId  рулевой, уже указанный в форме: если
     *                                      у команды не найден свой, исключается из экипажа
     * @return array{
     *     yacht_id: ?string,
     *     captain: ?array{id: string, name: string},
     *     members: list<array{user_id: string, name: string, role: string}>
     * }
     */
    public function handle(Team $team, ?string $regattaId, ?string $fallbackCaptainId = null): array
    {
        $lastEntry = $this->lastEntry($team, $regattaId);
        $busyUserIds = $this->busyUserIds($regattaId);

        $members = TeamMember::query()
            ->where('team_id', $team->id)
            ->where('status', 'active')
            ->with('user:id,name')
            ->get()
            ->filter(fn (TeamMember $m): bool => $m->user !== null
                && ! in_array((string) $m->user_id, $busyUserIds, true));

        // Роли участников в прошлой заявке: user_id → captain|main|reserve
        $lastRoles = $lastEntry?->crew
            ->mapWithKeys(fn (RegattaEntryCrew $c): array => [(string) $c->resolvedUserId() => $c->role])
            ->all() ?? [];

        $captainId = array_search('captain', $lastRoles, true);
        $captain = $captainId !== false
            ? $members->first(fn (TeamMember $m): bool => (string) $m->user_id === $captainId)?->user
            : null;

        $excludeId = $captain !== null ? (string) $captain->id : $fallbackCaptainId;

        return [
            'yacht_id' => $this->pickYachtId($team, $lastEntry, $regattaId),
            'captain' => $captain !== null ? ['id' => (string) $captain->id, 'name' => $captain->name] : null,
            'members' => $members
                ->reject(fn (TeamMember $m): bool => (string) $m->user_id === $excludeId)
                ->sortBy([
                    fn (TeamMember $a, TeamMember $b): int => $this->rank($a, $lastRoles) <=> $this->rank($b, $lastRoles),
                    fn (TeamMember $a, TeamMember $b): int => strcmp($a->user->name, $b->user->name),
                ])
                ->map(fn (TeamMember $m): array => [
                    'user_id' => (string) $m->user_id,
                    'name' => $m->user->name,
                    'role' => ($lastRoles[(string) $m->user_id] ?? null) === 'reserve' ? 'reserve' : 'main',
                ])
                ->values()
                ->all(),
        ];
    }

    /** Только яхта команды для регаты — при смене регаты в уже заполненной форме. */
    public function yachtId(Team $team, ?string $regattaId): ?string
    {
        return $this->pickYachtId($team, $this->lastEntry($team, $regattaId), $regattaId);
    }

    /** Последняя действующая заявка команды на другую (не удалённую) регату. */
    private function lastEntry(Team $team, ?string $regattaId): ?RegattaEntry
    {
        return RegattaEntry::query()
            ->where('team_id', $team->id)
            ->whereNotIn('status', self::INACTIVE_STATUSES)
            ->when($regattaId, fn ($q) => $q->where('regatta_id', '!=', $regattaId))
            ->whereHas('regatta')
            ->orderByDesc(Regatta::select('date_start')->whereColumn('regattas.id', 'regatta_entries.regatta_id'))
            ->with('crew.teamMember:id,user_id')
            ->first();
    }

    /** Чем меньше, тем выше в списке: экипаж прошлой регаты → постоянные → остальные. */
    private function rank(TeamMember $member, array $lastRoles): int
    {
        return match (true) {
            isset($lastRoles[(string) $member->user_id]) => 0,
            (bool) $member->is_permanent => 1,
            default => 2,
        };
    }

    private function pickYachtId(Team $team, ?RegattaEntry $lastEntry, ?string $regattaId): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            $team->default_yacht_id ? (string) $team->default_yacht_id : null,
            $lastEntry?->yacht_id ? (string) $lastEntry->yacht_id : null,
        ])));

        if ($candidates === []) {
            return null;
        }

        // Занятость — как в форме: яхта уже есть в любой заявке на эту регату.
        $taken = $regattaId
            ? RegattaEntry::query()
                ->where('regatta_id', $regattaId)
                ->whereIn('yacht_id', $candidates)
                ->pluck('yacht_id')
                ->map(fn ($id): string => (string) $id)
                ->all()
            : [];

        $existing = Yacht::withoutGlobalScope(OwnedScope::class)
            ->whereIn('id', $candidates)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        foreach ($candidates as $id) {
            if (in_array($id, $existing, true) && ! in_array($id, $taken, true)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Пользователи, уже заявленные на регату в действующих экипажах.
     *
     * @return list<string>
     */
    private function busyUserIds(?string $regattaId): array
    {
        if (! $regattaId) {
            return [];
        }

        return RegattaEntryCrew::query()
            ->whereHas('regattaEntry', fn ($q) => $q->where('regatta_id', $regattaId)
                ->whereNotIn('status', self::INACTIVE_STATUSES))
            ->with('teamMember:id,user_id')
            ->get()
            ->map(fn (RegattaEntryCrew $c): string => (string) $c->resolvedUserId())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
