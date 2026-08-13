<?php

namespace App\Models;

use App\Enums\ParticipationKind;
use App\Enums\RegattaStatus;
use App\Enums\RegattaType;
use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\Concerns\NormalizesHeicImageColumns;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Regatta extends Model
{
    use HasFactory, HasUuids, NormalizesHeicImageColumns, SoftDeletes;

    /** @var array<string> Строковые колонки-пути, где heic нормализуется в webp. */
    protected array $heicImageColumns = ['background_image'];

    protected $fillable = [
        'season_id',
        'series_id',
        'type',
        'name',
        'level_coefficient',
        'date_start',
        'date_end',
        'time_start',
        'time_end',
        'background_image',
        'location',
        'water_area',
        'short_description',
        'description',
        'coordinates',
        'schedule',
        'race_days_count',
        'races_count',
        'discards_count',
        'discard_1_after_races',
        'discard_2_after_races',
        'prizes',
        'entry_fee_required',
        'entry_fee_amount',
        'seat_price',
        'boat_price',
        'race_hours_per_day',
        'crew_size_limit',
        'external_id',
        'regatta_status',
        'postponed_to_date',
        'postponed_note',
        'postponed_to_regatta_id',
        'entry_required_documents',
    ];

    protected function casts(): array
    {
        return [
            'type' => RegattaType::class,
            'coordinates' => 'array',
            'level_coefficient' => 'decimal:2',
            'date_start' => 'date',
            'date_end' => 'date',
            'time_start' => 'datetime:H:i',
            'time_end' => 'datetime:H:i',
            'race_days_count' => 'integer',
            'races_count' => 'integer',
            'discards_count' => 'integer',
            'discard_1_after_races' => 'integer',
            'discard_2_after_races' => 'integer',
            'regatta_status' => RegattaStatus::class,
            'postponed_to_date' => 'date',
            'entry_required_documents' => 'array',
            'entry_fee_required' => 'boolean',
            'entry_fee_amount' => 'decimal:2',
            'seat_price' => 'decimal:2',
            'boat_price' => 'decimal:2',
            'race_hours_per_day' => 'decimal:1',
            'crew_size_limit' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }
    // ──────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $regatta) {
            // Автор регаты. `??=` обязателен: replicate() (ReplicateRegattaAction,
            // RegattaService::postpone) уже переносит user_id — не перетираем его.
            // Консоль, сидеры и импорт работают без auth() → регата остаётся «ничьей».
            $regatta->user_id ??= auth()->id();

            if ($regatta->external_id === null) {
                // Атомарно увеличиваем счетчик и забираем новое значение
                $sequence = DB::table('sequences')
                    ->where('name', 'regattas_external_id')
                    ->sharedLock() // Защита от race condition
                    ->first();

                $nextId = ($sequence ? $sequence->current_value : 0) + 1;

                DB::table('sequences')->updateOrInsert(
                    ['name' => 'regattas_external_id'],
                    ['current_value' => $nextId]
                );

                $regatta->external_id = $nextId;
            }
        });

        static::saving(function (self $regatta) {
            // Автоматически пересчитываем статус только при изменении дат/времени
            if (! $regatta->isDirty(['date_start', 'date_end', 'time_start', 'time_end'])) {
                return;
            }

            // Не сбрасываем ручные статусы
            if (in_array($regatta->regatta_status, [
                RegattaStatus::Cancelled,
                RegattaStatus::Postponed,
            ], true)) {
                return;
            }

            $now = now();
            $start = $regatta->startDateTime();
            $end = $regatta->endDateTime();

            if ($end && $end < $now) {
                $regatta->regatta_status = RegattaStatus::Finished;
            } elseif ($start && $end && $now->between($start, $end)) {
                $regatta->regatta_status = RegattaStatus::Active;
            } elseif ($start && $start > $now) {
                $regatta->regatta_status = RegattaStatus::Upcoming;
            }
        });

        static::saved(function (self $regatta) {
            // Смена настройки выброса меняет итоговые суммы и места —
            // пересчитываем протоколы и рейтинги регаты.
            if (! $regatta->wasChanged(['discards_count', 'discard_1_after_races', 'discard_2_after_races'])) {
                return;
            }

            $regatta->results()->get()->each(function (RegattaResult $result): void {
                RegattaResultResource::recomputeItemTotals($result);
                RegattaResultResource::recalculateRatings($result);
            });
        });
    }
    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** Автор регаты. NULL — регата «ничья», доступна только админам. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function races(): HasMany
    {
        return $this->hasMany(RegattaEvents::class)->orderBy('event_datetime');
    }

    /** Мероприятия расписания регаты (регистрация, открытие, брифинг и т.п.) */
    public function scheduleEvents(): HasMany
    {
        return $this->hasMany(RegattaScheduleEvent::class)
            ->orderBy('sort_order')
            ->orderBy('event_datetime');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RegattaEntry::class);
    }

    /** Одобренные заявки */
    public function approvedEntries(): HasMany
    {
        return $this->entries()->where('status', 'approved');
    }

    public function results(): HasMany
    {
        return $this->hasMany(RegattaResult::class);
    }

    /** Все позиции (items) регаты через результат */
    public function resultItems(): HasManyThrough
    {
        return $this->hasManyThrough(RegattaResultItem::class, RegattaResult::class);
    }

    /** Все результаты отдельных гонок регаты */
    public function raceResults(): HasManyThrough
    {
        return $this->hasManyThrough(RaceResult::class, RegattaEvents::class);
    }

    /** Документы регаты (положение, инструкции по гонкам, протоколы) */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Альбомы регаты */
    public function albums(): MorphMany
    {
        return $this->morphMany(Album::class, 'albumable');
    }

    public function postponedToRegatta(): BelongsTo
    {
        return $this->belongsTo(self::class, 'postponed_to_regatta_id');
    }

    /** Объявления бирж, поданные на эту регату (@see Advert) */
    public function adverts(): BelongsToMany
    {
        return $this->belongsToMany(Advert::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Ограничивает выборку регатами, доступными пользователю.
     *
     * Сужается только роль «Админ-разработчик» — она видит лишь свои регаты.
     * Все прочие роли, а также гости и публичный сайт, проходят насквозь,
     * поэтому применение скоупа вне админки безвредно.
     */
    public function scopeVisibleForUser(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user?->isDeveloperAdmin()) {
            return $query;
        }

        return $query->where($query->qualifyColumn('user_id'), $user->id);
    }

    /** Фильтр по типу соревнования: клубные / регулярные / выездные. */
    public function scopeOfType(Builder $query, RegattaType|string|null $type): Builder
    {
        if ($type === null) {
            return $query;
        }

        return $query->where(
            $query->qualifyColumn('type'),
            $type instanceof RegattaType ? $type->value : $type,
        );
    }

    public function scopeUpcoming($query)
    {
        $today = now()->format('Y-m-d');
        $time = now()->format('H:i:s');

        return $query->where(function ($q) use ($today, $time) {
            $q->where('date_start', '>', $today)
                ->orWhere(function ($q) use ($today, $time) {
                    $q->where('date_start', '=', $today)
                        ->whereRaw("COALESCE(time_start, '12:00:00') >= ?", [$time]);
                });
        });
    }

    public function scopeClosest($query)
    {
        return $query->upcoming()
            ->whereNotIn('regatta_status', [
                RegattaStatus::Cancelled,
                RegattaStatus::Postponed,
            ])
            ->orderBy('date_start')
            ->orderByRaw("COALESCE(time_start, '12:00:00') ASC");
    }

    /**
     * Активные + Ближайшие регаты.
     *
     * Возвращает регаты, которые либо сейчас идут (Active),
     * либо будут ближайшими (Closest/Upcoming) —
     * исключая Finished, Cancelled, Postponed.
     */
    public function scopeActiveAndClosest($query)
    {
        return $query
            ->whereNotIn('regatta_status', [
                RegattaStatus::Cancelled,
                RegattaStatus::Postponed,
                RegattaStatus::Finished,
            ])
            ->orderBy('date_start')
            ->orderByRaw("COALESCE(time_start, '12:00:00') ASC");
    }

    public function pruningScope(): Builder
    {
        // Удаляем записи, которые были "мягко удалены" более 7 дней назад
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(7));
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Список регат для Select в модалках экспорта: сначала ближайшие/активные,
     * затем прочие по дате старта. Значение — «Название • дд.мм.гггг».
     *
     * @return array<int, string>
     */
    public static function exportSelectOptions(): array
    {
        $priority = [
            RegattaStatus::Closest->value => 0,
            RegattaStatus::Active->value => 1,
            RegattaStatus::Upcoming->value => 2,
            RegattaStatus::Postponed->value => 3,
            RegattaStatus::Finished->value => 4,
            RegattaStatus::Cancelled->value => 5,
        ];

        return static::query()
            ->visibleForUser()
            ->get()
            ->sortBy(fn (self $r): string => sprintf(
                '%02d-%011d',
                $priority[$r->regatta_status?->value] ?? 99,
                $r->date_start?->timestamp ?? 0,
            ))
            ->mapWithKeys(fn (self $r): array => [
                $r->id => $r->name.' • '.($r->date_start?->format('d.m.Y') ?? '—'),
            ])
            ->all();
    }

    /**
     * Предельный размер экипажа в заявке.
     *
     * Регулярные регаты ограничены шестью местами на лодке ассоциации,
     * выездные — характеристиками флота (поле заполняет организатор),
     * клубные не ограничены (NULL).
     */
    public function maxCrewSize(): ?int
    {
        return $this->type === RegattaType::Travel
            ? $this->crew_size_limit
            : $this->type->crewSizeLimit();
    }

    /** Можно ли заявиться одному, без экипажа. */
    public function allowsIndividualEntry(): bool
    {
        return $this->type->allowsIndividualEntry();
    }

    /** Проходят ли заявки на эту регату проверку администратором. */
    public function requiresEntryModeration(): bool
    {
        return $this->type->requiresModeration();
    }

    /**
     * Стоимость заявки: лодка целиком для экипажа, место × число человек
     * для индивидуальной. Возвращает NULL, если цены у регаты не заданы —
     * тогда сумму выставляет администратор вручную.
     */
    public function entryPrice(ParticipationKind $kind, int $peopleCount = 1): ?float
    {
        $price = $kind === ParticipationKind::Individual
            ? $this->seat_price
            : $this->boat_price;

        if ($price === null) {
            return null;
        }

        return $kind === ParticipationKind::Individual
            ? (float) $price * max($peopleCount, 1)
            : (float) $price;
    }

    public function isUpcoming(): bool
    {
        $start = $this->startDateTime();

        return $start && $start->isFuture();
    }

    /** Get the closest upcoming regatta by start date */
    public static function closestUpcoming(): ?self
    {
        return static::closest()->first();
    }

    public static function closestUpcomingAndActive(): ?self
    {
        return static::activeAndClosest()->first();
    }

    public function startsInLessThanMonth(): bool
    {
        $start = $this->startDateTime();

        return $start && $start->isFuture() && now()->diffInDays($start, false) < 30;
    }

    public function isActive(): bool
    {
        $start = $this->startDateTime();
        $end = $this->endDateTime();

        return $start && $end && now()->between($start, $end);
    }

    public function isFinished(): bool
    {
        $end = $this->endDateTime();

        return $end && $end->isPast();
    }

    /** Открыта ли регата для подачи заявок (не завершена, не отменена, не перенесена). */
    public function isOpenForRegistration(): bool
    {
        return ! in_array($this->regatta_status, [
            RegattaStatus::Finished,
            RegattaStatus::Cancelled,
            RegattaStatus::Postponed,
        ], true);
    }

    /**
     * Комбинирует date_start + time_start (дефолт 12:00).
     */
    public function startDateTime(): ?Carbon
    {
        if (! $this->date_start) {
            return null;
        }
        $time = $this->time_start ? $this->time_start->format('H:i') : '12:00';

        return $this->date_start->copy()->setTimeFromTimeString($time);
    }

    /**
     * Комбинирует date_end + time_end (дефолт 12:00).
     */
    public function endDateTime(): ?Carbon
    {
        if (! $this->date_end) {
            return null;
        }
        $time = $this->time_end ? $this->time_end->format('H:i') : '12:00';

        return $this->date_end->copy()->setTimeFromTimeString($time);
    }

    public function hasTeam(Team $team): bool
    {
        return $this->entries()
            ->where('team_id', $team->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
    }

    /**
     * Положение регаты в серии и общее число регат в серии.
     *
     * Регаты сортируются по дате старта (затем времени старта).
     *
     * @return array{position: int, total: int}|null
     */
    public function seriesPosition(): ?array
    {
        if (! $this->series_id) {
            return null;
        }

        $regattas = $this->series?->regattas;

        if ($regattas === null || $regattas->isEmpty()) {
            return null;
        }

        $sorted = $regattas
            ->sortBy([
                ['date_start', 'asc'],
                ['time_start', 'asc'],
            ])
            ->values();

        $position = $sorted->search(fn (self $r) => $r->id === $this->id);

        if ($position === false) {
            return null;
        }

        return [
            'position' => $position + 1,
            'total' => $sorted->count(),
        ];
    }

    /** Human-friendly date range for the regatta */
    public function dateRange(): string
    {
        $start = $this->date_start;
        $end = $this->date_end;

        if (! $start) {
            return '';
        }

        // Single day event: "19.05.2026"
        if (! $end || $start->isSameDay($end)) {
            return $start->format('d.m.Y');
        }

        // Date range: "14.05.2026 – 16.05.2026"
        return $start->format('d.m.Y').' – '.$end->format('d.m.Y');
    }

    /**
     * Нормализованный список документов для заявок.
     *
     * Поддерживает обратную совместимость: старый формат ['key1', 'key2']
     * преобразуется в новый [{doc_type, is_required}].
     *
     * @return array<int, array{doc_type: string, title: string, is_required: bool}>
     */
    public function getEntryDocuments(): array
    {
        $raw = $this->entry_required_documents;

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $types = YachtDocumentType::cachedConfigurable();

        // Старый формат — плоский массив строк ['orc_certificate', 'ship_ticket']
        if (isset($raw[0]) && is_string($raw[0])) {
            return $types
                ->filter(fn (YachtDocumentType $t) => in_array($t->key, $raw, true))
                ->map(fn (YachtDocumentType $t) => [
                    'doc_type' => $t->key,
                    'title' => $t->label,
                    'is_required' => true,
                ])
                ->values()
                ->all();
        }

        // Новый формат — массив объектов [{doc_type, is_required}]
        return collect($raw)
            ->map(function (array $item) use ($types) {
                $type = $types->first(fn (YachtDocumentType $t) => $t->key === ($item['doc_type'] ?? ''));

                return $type ? [
                    'doc_type' => $type->key,
                    'title' => $type->label,
                    'is_required' => (bool) ($item['is_required'] ?? false),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
