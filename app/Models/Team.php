<?php

declare(strict_types=1);

namespace App\Models;

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

class Team extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /** Максимальное количество активных участников в команде */
    public const int MAX_MEMBERS = 10;

    protected $fillable = [
        'name',
        'description',
        'organizer_id',
        'default_yacht_id',
        'is_archived',
        'picture',
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
            ->withPivot(['role', 'status', 'joined_at'])
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

    /** Альбомы (галерея) команды */
    public function albums(): MorphMany
    {
        return $this->morphMany(Album::class, 'albumable');
    }

    /** Итоговые результаты по регатам (через RegattaResultItem) */
    public function regattaResultItems(): HasMany
    {
        return $this->hasMany(RegattaResultItem::class);
    }

    /** Рейтинги по сезонам */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
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
        if ($user->system_role !== 'user') {
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
}
