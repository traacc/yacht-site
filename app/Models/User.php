<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Enums\SportCategory;
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'birth_date',
        'sport_category',
        'email',
        'phone',
        'password',
        'photo_url',
        'system_role',
        'external_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'birth_date'        => 'date',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password'          => 'hashed',
            'sport_category'    => SportCategory::class,
        ];
    }

    // ──────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if ($user->external_id === null) {
                $user->external_id = DB::transaction(function () {
                    $max = static::lockForUpdate()->max('external_id') ?? 0;
                    return $max + 1;
                });
            }
        });
    }

    // ──────────────────────────────────────────────
    // Computed attributes
    // ──────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: function (?string $value) {
                return [
                    'first_name' => $value,
                    'name'       => trim(($value ?? '') . ' ' . ($this->last_name ?? '')),
                ];
            }
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: function (?string $value) {
                return [
                    'last_name' => $value,
                    'name'      => trim(($this->first_name ?? '') . ' ' . ($value ?? '')),
                ];
            }
        );
    }

    // ──────────────────────────────────────────────
    // Role helpers
    // ──────────────────────────────────────────────

    public function isAdmin(): bool      { return $this->system_role === 'admin'; }
    public function isJudge(): bool      { return $this->system_role === 'judge'; }
    public function isSecretary(): bool  { return $this->system_role === 'secretary'; }
    public function isAccountant(): bool { return $this->system_role === 'accountant'; }

    /**
     * Является ли пользователь капитаном (организатором) хотя бы одной команды.
     */
    public function isCaptain(): bool
    {
        return $this->organisedTeams()->exists();
    }

    /**
     * Scope: пользователи, не состоящие ни в одной активной команде.
     */
    public function scopeFreeUsers(Builder $query): Builder
    {
        return $query->whereDoesntHave('teamMemberships', fn (Builder $q) =>
            $q->where('status', 'active')
        );
    }


    // Дней до следующего дня рождения
    protected function daysUntilBirthday(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->birth_date) return null;

                $today = Carbon::today();
                $next = $this->birth_date->copy()->setYear($today->year);

                if ($next->isPast() && !$next->isToday()) {
                    $next->addYear();
                }

                return $today->diffInDays($next);
            }
        );
    }

    // Следующий день рождения (дата)
    protected function nextBirthday(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->birth_date) return null;

                $today = Carbon::today();
                $next = $this->birth_date->copy()->setYear($today->year);

                if ($next->isPast() && !$next->isToday()) {
                    $next->addYear();
                }

                return $next;
            }
        );
    }

    public function scopeOrderByUpcomingBirthday(Builder $query): Builder
    {
        return $query->orderByRaw("
            CASE WHEN birth_date IS NULL THEN 99999 ELSE
                MOD(
                    DAYOFYEAR(
                        IF(
                            DATE_FORMAT(birth_date, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d'),
                            CONCAT(YEAR(NOW()), DATE_FORMAT(birth_date, '-%m-%d')),
                            CONCAT(YEAR(NOW()) + 1, DATE_FORMAT(birth_date, '-%m-%d'))
                        )
                    ) - DAYOFYEAR(NOW()) + 365,
                    365
                )
            END ASC
        ");
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** Команды, в которых пользователь состоит (через промежуточную таблицу) */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
                    ->using(TeamMember::class)
                    ->withPivot(['role', 'status', 'joined_at'])
                    ->withTimestamps();
    }

    /** Команды, которые пользователь создал */
    public function organisedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'organizer_id');
    }

    /** Записи участия в командах */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** Яхты зарегистрированные пользователем */
    public function yachts(): HasMany
    {
        return $this->hasMany(Yacht::class, 'user_id');
    }

    /** Личный рейтинг по сезонам */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /** Новости, опубликованные пользователем */
    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'author_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {

        if ($this->isAdmin()) {
            return $this->system_role === 'admin'; 
        }
        if ($panel->getId() === 'user') {
            return true;
        }

        return false;
    }
}
