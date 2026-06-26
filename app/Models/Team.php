<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemRole;
use App\Enums\TeamMemberRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use Illuminate\Support\Facades\DB;

class Team extends Model implements HasMedia
{
    use HasFactory, HasUuids, SoftDeletes, InteractsWithMedia;

    /** Максимальное количество активных участников в команде */
    public const int MAX_MEMBERS = 50;

    protected $fillable = [
        'name',
        'description',
        'organizer_id',
        'default_yacht_id',
        'is_archived',
        'picture',
        'external_id',
        'approval_status',
        'rejection_reason',
    ];


    
    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }
    // ──────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $team) {
            if ($team->external_id === null) {
                // Атомарно увеличиваем счетчик и забираем новое значение
                    $sequence = DB::table('sequences')
                        ->where('name', 'teams_external_id')
                        ->sharedLock() // Защита от race condition
                        ->first();

                    $nextId = ($sequence ? $sequence->current_value : 0) + 1;

                    DB::table('sequences')->updateOrInsert(
                        ['name' => 'teams_external_id'],
                        ['current_value' => $nextId]
                    );

                    $team->external_id = $nextId;
            }
        });
    }
    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** Организатор (создатель) команды */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /** Яхта по умолчанию для команды */
    public function defaultYacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'default_yacht_id');
    }

    /** Все участники через pivot */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->using(TeamMember::class)
            ->withPivot(['id', 'role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /** Только активные участники */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    /** Pivot-записи участия */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** Заявки на регаты */
    public function regattaEntries(): HasMany
    {
        return $this->hasMany(RegattaEntry::class);
    }

    // ──────────────────────────────────────────────
    // Media Library
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
             ->useDisk('public');
    }

    // ──────────────────────────────────────────────
    // Relationships (продолжение)
    // ──────────────────────────────────────────────

    /** Итоговые результаты по регатам (через RegattaResultItem) */
    public function regattaResultItems(): HasMany
    {
        return $this->hasMany(RegattaResultItem::class);
    }

    /** Командные рейтинги по сезонам */
    public function teamRatings(): HasMany
    {
        return $this->hasMany(TeamRating::class);
    }

    /** Платежи команды (годовой сбор и т.п.) — полиморфная связь payable. */
    public function paymentRegistries(): MorphMany
    {
        return $this->morphMany(PaymentRegistry::class, 'payable');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    public function getFormattedExternalIdAttribute(): string
    {
        if ($this->external_id === null) {
            return '—';
        }

        return 'K' . str_pad((string) $this->external_id, 4, '0', STR_PAD_LEFT);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function hasMember(User $user): bool
    {
        return $this->activeMembers()->where('users.id', $user->id)->exists();
    }

    public function getMemberRole(User $user): ?string
    {
        return $this->teamMembers()
            ->where('user_id', $user->id)
            ->value('role');
    }

    /**
     * Возвращает роль пользователя в команде как enum-значение.
     * Учитывает только активных участников.
     */
    public function getMemberRoleEnum(User $user): ?TeamMemberRole
    {
        $roleValue = $this->teamMembers()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('role');

        return $roleValue !== null ? TeamMemberRole::tryFrom($roleValue) : null;
    }

    public function scopeVisibleForUser(Builder $query, User $user): Builder
    {
        // Staff (admin, judge, secretary, accountant) видят всё
        if ($user->system_role !== SystemRole::User) {
            return $query;
        }

        // Обычный пользователь — только свои команды
        return $query->where(function (Builder $q) use ($user) {
            $q->where('organizer_id', $user->id)                    // созданные им
                ->orWhereHas('members', fn (Builder $q) => $q          // где он участник
                    ->where('user_id', $user->id)
                );
        });
    }
    public function pruningScope(): Builder
    {
        // Удаляем записи, которые были "мягко удалены" более 7 дней назад
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(7));
    }
}
