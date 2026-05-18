<?php

namespace App\Models;

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

    protected $fillable = [
        'name',
        'description',
        'organizer_id',
        'is_archived',
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

    /** Итоговые результаты по регатам */
    public function regattaResults(): HasMany
    {
        return $this->hasMany(RegattaResult::class);
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
