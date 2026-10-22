<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\RegattaStatus;
use App\Enums\SportCategory;
use App\Enums\SystemRole;
use App\Enums\TeamMemberRole;
use App\Models\CrewJoinRequest;
use App\Models\PersonalRating;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaEntryCrew;
use App\Models\RegattaResultItem;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamMemberInvitation;
use App\Models\User;
use App\Models\UserReplacement;
use App\Services\RatingCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Замена одного пользователя другим во всех заявках (включая архивные) и командах.
 *
 * Типичный случай — в быстрой заявке участника внесли вручную, и для него
 * создалась вторая учётка, хотя он уже зарегистрирован. Экипаж заявки ссылается
 * на участника команды (team_members), поэтому заменяется членство в команде,
 * а не только строки экипажа.
 *
 * Если человек оказался в одном экипаже под обеими учётками, остаётся одна
 * строка со старшей ролью (рулевой > основной > запасной). После замены
 * пересчитывается личный рейтинг затронутых сезонов. Старая учётка мягко
 * удаляется, если на неё больше ничего не ссылается (яхты, голоса, платежи...).
 */
final class ReplaceUserAction
{
    /** Старшинство ролей в экипаже: чем меньше индекс, тем старше. */
    private const CREW_ROLES = ['captain', 'main', 'reserve'];

    /** Старшинство ролей в команде. */
    private const TEAM_ROLES = [
        TeamMemberRole::Organizer->value,
        TeamMemberRole::TeamAdmin->value,
        TeamMemberRole::Member->value,
    ];

    /**
     * Связи, которые замена не переносит: пока они есть, учётку не удаляем.
     *
     * @var list<array{0: string, 1: string, 2: string}> [таблица, колонка, подпись]
     */
    private const BLOCKING_LINKS = [
        ['yachts', 'user_id', 'яхты'],
        ['yacht_rental_requests', 'user_id', 'заявки на аренду яхт'],
        ['yacht_ownership_transfers', 'requester_id', 'передачи владения яхтами'],
        ['yacht_ownership_transfers', 'previous_owner_id', 'передачи владения яхтами'],
        ['votes', 'user_id', 'голоса'],
        ['payment_transactions', 'user_id', 'онлайн-платежи'],
        ['adverts', 'user_id', 'объявления'],
        ['user_questions', 'user_id', 'вопросы'],
        ['feedback_requests', 'user_id', 'обращения'],
        ['conversation_participants', 'user_id', 'переписка в чате'],
        ['repair_requests', 'user_id', 'заявки на ремонт'],
        ['service_requests', 'user_id', 'заявки на услуги'],
        ['telegram_accounts', 'user_id', 'привязка Telegram'],
        ['news', 'author_id', 'новости'],
        ['regattas', 'user_id', 'регаты'],
        ['votings', 'created_by', 'голосования'],
    ];

    public function __construct(private readonly RatingCalculator $ratings) {}

    /**
     * Что изменит замена — для окна подтверждения и журнала.
     *
     * @return array{
     *     entries: list<array{regatta: string, date: ?string, team: ?string, role: string, merged: bool, archived: bool}>,
     *     submitted: int, teams: list<array{team: string, merged: bool}>,
     *     snapshots: int, join_requests: int, invitations: int,
     *     seasons: list<string>, blockers: list<string>
     * }
     */
    public function preview(User $from, User $to): array
    {
        $entries = $this->crewRowsOf($from)
            ->with(['regattaEntry.regatta', 'regattaEntry.team'])
            ->get()
            ->sortByDesc(fn (RegattaEntryCrew $row) => $row->regattaEntry?->regatta?->date_start)
            ->map(function (RegattaEntryCrew $row) use ($to): array {
                $entry = $row->regattaEntry;
                $regatta = $entry?->regatta;

                return [
                    'regatta' => $regatta?->name ?? '—',
                    'date' => $regatta?->date_start?->format('d.m.Y'),
                    'team' => $entry?->team?->name,
                    'role' => $row->roleLabel(),
                    'merged' => $this->crewRowsOf($to)->where('regatta_entry_id', $row->regatta_entry_id)->exists(),
                    'archived' => in_array($regatta?->regatta_status, [
                        RegattaStatus::Finished, RegattaStatus::Cancelled, RegattaStatus::Postponed,
                    ], true),
                ];
            })
            ->values()
            ->all();

        $teams = TeamMember::query()
            ->where('user_id', $from->id)
            ->with('team')
            ->get()
            ->map(fn (TeamMember $membership): array => [
                'team' => $membership->team?->name ?? '—',
                'merged' => TeamMember::query()
                    ->where('team_id', $membership->team_id)
                    ->where('user_id', $to->id)
                    ->exists(),
            ])
            ->all();

        return [
            'entries' => $entries,
            'submitted' => RegattaEntry::query()->where('user_id', $from->id)->count(),
            'teams' => $teams,
            'snapshots' => $this->snapshotItemsOf($from)->count(),
            'join_requests' => CrewJoinRequest::query()->where('user_id', $from->id)->count(),
            'invitations' => TeamMemberInvitation::query()->where('user_id', $from->id)->count(),
            'seasons' => Season::query()
                ->whereKey($this->affectedSeasonIds($from))
                ->orderBy('year')
                ->pluck('year')
                ->map(fn ($year): string => (string) $year)
                ->all(),
            'blockers' => $this->blockers($from),
        ];
    }

    public function handle(User $from, User $to, User $actor): UserReplacement
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_id' => 'Нельзя заменить пользователя на самого себя.']);
        }

        if ($from->trashed() || $to->trashed()) {
            throw ValidationException::withMessages(['to_id' => 'Одна из учёток удалена — сначала восстановите её.']);
        }

        return DB::transaction(function () use ($from, $to, $actor): UserReplacement {
            // Снимок «до» — после замены строки уже не найти по старой учётке.
            $summary = $this->preview($from, $to);
            $seasonIds = $this->affectedSeasonIds($from);

            $this->replaceInCrews($from, $to);
            $this->replaceTeamMemberships($from, $to);
            $this->replaceInResultSnapshots($from, $to);

            // Массовое обновление: событий заявки (письма модерации, ссылки
            // на оплату) смена подающего вызывать не должна.
            RegattaEntry::query()->where('user_id', $from->id)->update(['user_id' => $to->id]);
            CrewJoinRequest::query()->where('user_id', $from->id)->update(['user_id' => $to->id]);
            TeamMemberInvitation::query()->where('user_id', $from->id)->update(['user_id' => $to->id]);
            Team::query()->where('organizer_id', $from->id)->update(['organizer_id' => $to->id]);

            Season::query()
                ->whereKey($seasonIds)
                ->get()
                ->each(fn (Season $season) => $this->ratings->recalculateForSeason($season));

            $blockers = $this->blockers($from);
            $summary['blockers'] = $blockers;

            if ($blockers === []) {
                $from->delete();
            }

            return UserReplacement::create([
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'from_name' => $from->name,
                'to_name' => $to->name,
                'from_deleted' => $blockers === [],
                'summary' => $summary,
                'performed_by' => $actor->id,
                'performed_by_name' => $actor->name,
            ]);
        });
    }

    /**
     * Строки экипажей, за которыми стоит пользователь — по той же логике,
     * что RegattaEntryCrew::resolvedUserId(): прямая ссылка важнее членства в команде.
     */
    private function crewRowsOf(User $user): Builder
    {
        return RegattaEntryCrew::query()->where(fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->orWhere(fn (Builder $q) => $q
                ->whereNull('user_id')
                ->whereHas('teamMember', fn (Builder $q) => $q->where('user_id', $user->id))));
    }

    /**
     * Строки экипажей с прямой ссылкой на пользователя переводим на нового;
     * если новый уже в этом экипаже — оставляем его строку со старшей ролью.
     * Строки через участника команды переедут вместе с членством.
     */
    private function replaceInCrews(User $from, User $to): void
    {
        foreach ($this->crewRowsOf($from)->get() as $row) {
            $existing = $this->crewRowsOf($to)
                ->where('regatta_entry_id', $row->regatta_entry_id)
                ->first();

            if ($existing !== null) {
                $existing->update(['role' => $this->senior(self::CREW_ROLES, $existing->role, $row->role)]);
                $row->delete();

                continue;
            }

            if ($row->user_id === $from->id) {
                $row->update(['user_id' => $to->id]);
            }
        }
    }

    /**
     * Членство в команде переходит новому пользователю. Если он уже в этой
     * команде — сливаем: старшая роль, «постоянный» и «активный», если был
     * хоть у одного, строки экипажей переводим на его членство.
     */
    private function replaceTeamMemberships(User $from, User $to): void
    {
        foreach (TeamMember::query()->where('user_id', $from->id)->get() as $membership) {
            $target = TeamMember::query()
                ->where('team_id', $membership->team_id)
                ->where('user_id', $to->id)
                ->first();

            if ($target === null) {
                // Через модель: TeamMemberObserver перенесёт organizer_id команды.
                $membership->update(['user_id' => $to->id]);

                continue;
            }

            $target->update([
                'role' => $this->senior(self::TEAM_ROLES, $target->role, $membership->role),
                'status' => $membership->isActive() ? 'active' : $target->status,
                'is_permanent' => $target->is_permanent || $membership->is_permanent,
                'joined_at' => collect([$target->joined_at, $membership->joined_at])->filter()->min(),
            ]);

            RegattaEntryCrew::query()
                ->where('team_member_id', $membership->id)
                ->update(['team_member_id' => $target->id]);

            $membership->delete();
        }
    }

    /**
     * Снимки состава в «замороженных» строках протоколов (заявка удалена)
     * ссылаются на пользователя по id — обновляем данные участника.
     */
    private function replaceInResultSnapshots(User $from, User $to): void
    {
        $this->snapshotItemsOf($from)->get()->each(function (RegattaResultItem $item) use ($from, $to): void {
            $crew = collect($item->crew_snapshot)
                ->map(fn (array $member): array => ($member['id'] ?? null) === $from->id
                    ? array_merge($member, [
                        'id' => $to->id,
                        'name' => $to->name,
                        'birthday' => $to->birth_date?->format('d.m.Y') ?? '—',
                        'rank' => SportCategory::labelOrNone($to->sport_category),
                        'avatar' => $to->photo_url ? asset('storage/'.$to->photo_url) : null,
                    ])
                    : $member)
                // Оба в одном экипаже — одна запись со старшей ролью.
                ->groupBy(fn (array $member, int $i): string => $member['id'] ?? "guest-{$i}")
                ->map(fn ($same): array => $same->reduce(fn (?array $carry, array $member): array => $carry === null
                    ? $member
                    : array_merge($carry, ['role' => $this->senior(self::CREW_ROLES, $carry['role'] ?? null, $member['role'] ?? null)])))
                ->values()
                ->all();

            $item->forceFill([
                'crew_snapshot' => $crew,
                'captain_name' => collect($crew)->firstWhere('role', 'captain')['name'] ?? $item->captain_name,
            ])->saveQuietly();
        });
    }

    private function snapshotItemsOf(User $user): Builder
    {
        return RegattaResultItem::query()->whereJsonContains('crew_snapshot', ['id' => $user->id]);
    }

    /**
     * Сезоны, где у пользователя есть личный рейтинг или участие в экипаже.
     *
     * @return list<string>
     */
    private function affectedSeasonIds(User $user): array
    {
        $crewEntryIds = $this->crewRowsOf($user)->select('regatta_entry_id');

        return Regatta::query()
            ->whereHas('entries', fn (Builder $q) => $q->whereIn('regatta_entries.id', $crewEntryIds))
            ->whereNotNull('season_id')
            ->pluck('season_id')
            ->merge(PersonalRating::query()->where('user_id', $user->id)->pluck('season_id'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Почему учётку нельзя удалить после замены.
     *
     * @return list<string>
     */
    private function blockers(User $user): array
    {
        $counts = [];

        foreach (self::BLOCKING_LINKS as [$table, $column, $label]) {
            $counts[$label] = ($counts[$label] ?? 0) + DB::table($table)->where($column, $user->id)->count();
        }

        $reasons = collect($counts)
            ->filter()
            ->map(fn (int $count, string $label): string => "{$label}: {$count}")
            ->values()
            ->all();

        if ($user->system_role !== SystemRole::User) {
            array_unshift($reasons, 'системная роль «'.$user->system_role->getLabel().'»');
        }

        return $reasons;
    }

    /** Старшая из двух ролей по списку старшинства. */
    private function senior(array $order, ?string $a, ?string $b): ?string
    {
        $rank = fn (?string $role): int => ($i = array_search($role, $order, true)) === false ? PHP_INT_MAX : $i;

        return $rank($a) <= $rank($b) ? $a : $b;
    }
}
