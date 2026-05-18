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
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

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
        ];
    }

    // ──────────────────────────────────────────────
    // Computed attributes
    // ──────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // ──────────────────────────────────────────────
    // Role helpers
    // ──────────────────────────────────────────────

    public function isAdmin(): bool      { return $this->system_role === 'admin'; }
    public function isJudge(): bool      { return $this->system_role === 'judge'; }
    public function isSecretary(): bool  { return $this->system_role === 'secretary'; }
    public function isAccountant(): bool { return $this->system_role === 'accountant'; }

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
