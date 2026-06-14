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
use App\Enums\SystemRole;
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'patronymic',
        'birth_date',
        'sport_category',
        'email',
        'phone',
        'password',
        'photo_url',
        'system_role',
        'external_id',
    ];

    protected $attributes = [
        'first_name' => '',
        'last_name'  => '',
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
            'system_role'       => SystemRole::class,
        ];
    }

    /**
     * Отправить пользователю письмо для восстановления пароля
     * (брендированное письмо на русском вместо стандартного уведомления Laravel).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        \Illuminate\Support\Facades\Mail::to($this->email)
            ->send(new \App\Mail\ResetPasswordMail($this, $token));
    }

    // ──────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (self $user) {
            // Уникальность пользователя по сочетанию ФИО + дата рождения
            if (blank($user->name) || blank($user->birth_date)) {
                return;
            }

            $duplicateExists = static::query()
                ->where('name', $user->name)
                ->whereDate('birth_date', $user->birth_date)
                ->when($user->exists, fn (Builder $q) => $q->whereKeyNot($user->getKey()))
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'name' => 'Пользователь с таким ФИО и датой рождения уже зарегистрирован',
                ]);
            }
        });

        static::creating(function (self $user) {
            if ($user->external_id === null) {
                // Атомарно увеличиваем счетчик и забираем новое значение
                $sequence = DB::table('sequences')
                    ->where('name', 'users_external_id')
                    ->sharedLock() // Защита от race condition
                    ->first();

                $nextId = ($sequence ? $sequence->current_value : 0) + 1;

                DB::table('sequences')->updateOrInsert(
                    ['name' => 'users_external_id'],
                    ['current_value' => $nextId]
                );

                $user->external_id = $nextId;
            }
        });
    }

    // ──────────────────────────────────────────────
    // Computed attributes
    // ──────────────────────────────────────────────

    public function getFormattedExternalIdAttribute(): string
    {
        if ($this->external_id === null) {
            return '—';
        }

        return str_pad((string) $this->external_id, 4, '0', STR_PAD_LEFT);
    }

    public function getFullNameAttribute(): string
    {
        return trim(" {$this->last_name} {$this->first_name} {$this->patronymic}");
    }
    /*
    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: function (?string $value) {
                return [
                    'first_name' => $value,
                    'name'       => trim(
                        ($this->last_name ?? '') . ' ' .
                        ($value ?? '') . ' ' .
                        ($this->patronymic ?? '')
                    ),
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
                    'name'      => trim(
                        ($value ?? '') . ' ' .
                        ($this->first_name ?? '') . ' ' .
                        ($this->patronymic ?? '')
                    ),
                ];
            }
        );
    }

    protected function patronymic(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: function (?string $value) {
                return [
                    'patronymic' => $value,
                    'name'       => trim(
                        ($this->last_name ?? '') . ' ' .
                        ($this->first_name ?? '') . ' ' .
                        ($value ?? '')
                    ),
                ];
            }
        );
    }
    */
    // ──────────────────────────────────────────────
    // Role helpers
    // ──────────────────────────────────────────────

    public function isAdmin(): bool      { return $this->system_role === SystemRole::Admin; }
    public function isJudge(): bool      { return $this->system_role === SystemRole::Judge; }
    public function isSecretary(): bool  { return $this->system_role === SystemRole::Secretary; }
    public function isAccountant(): bool { return $this->system_role === SystemRole::Accountant; }

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

    /**
     * Scope: пользователи, не являющиеся постоянными участниками других команд.
     */
    public function scopeWithoutPermanentInOtherTeams(Builder $query, $exceptTeamId = null): Builder
    {
        return $query->whereDoesntHave('teamMemberships', function (Builder $q) use ($exceptTeamId) {
            $q->where('is_permanent', true);

            if ($exceptTeamId) {
                $q->where('team_id', '!=', $exceptTeamId);
            }
        });
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

                return (int) $today->diffInDays($next);
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
        if ($panel->getId() === 'admin') {
            return $this->isAdmin() || $this->isJudge() || $this->isSecretary() || $this->isAccountant();
        }

        if ($panel->getId() === 'user') {
            return true;
        }

        return false;
    }

    public function pruningScope(): Builder
    {
        // Удаляем записи, которые были "мягко удалены" более 7 дней назад
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(7));
    }
}
