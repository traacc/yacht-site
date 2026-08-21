<?php

namespace App\Models;

use App\Enums\CreationSource;
use App\Enums\SportCategory;
use App\Enums\SystemRole;
use App\Mail\ResetPasswordMail;
use App\Mail\VerifyEmailMail;
use App\Models\Concerns\NormalizesHeicImageColumns;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, HasUuids, NormalizesHeicImageColumns, Notifiable, SoftDeletes;

    /** @var array<string> Строковые колонки-пути, где heic нормализуется в webp. */
    protected array $heicImageColumns = ['photo_url'];

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'patronymic',
        'birth_date',
        'sport_category',
        'about',
        'email',
        'phone',
        'password',
        'photo_url',
        'system_role',
        'creation_source',
        'external_id',
    ];

    protected $attributes = [
        'first_name' => '',
        'last_name' => '',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'sport_category' => SportCategory::class,
            'system_role' => SystemRole::class,
            'creation_source' => CreationSource::class,
        ];
    }

    /**
     * Отправить пользователю письмо для восстановления пароля
     * (брендированное письмо на русском вместо стандартного уведомления Laravel).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        Mail::to($this->email)
            ->send(new ResetPasswordMail($this, $token));
    }

    // ──────────────────────────────────────────────
    // Подтверждение e-mail
    // ──────────────────────────────────────────────

    /**
     * Технический адрес, сгенерированный при быстрой заявке для участника
     * без собственной почты. На такие адреса письма не отправляются.
     */
    public function hasTechnicalEmail(): bool
    {
        return $this->email === null || str_ends_with($this->email, '@noemail.local');
    }

    /**
     * Отправить письмо для подтверждения e-mail
     * (брендированное письмо на русском вместо стандартного уведомления Laravel).
     */
    public function sendEmailVerificationNotification(): void
    {
        if ($this->hasTechnicalEmail()) {
            return;
        }

        Mail::to($this->email)
            ->send(new VerifyEmailMail($this));
    }

    /**
     * Отметить e-mail подтверждённым.
     *
     * Переопределено: стандартная реализация вызывает save(), а хук saving()
     * бросает ValidationException при дубле ФИО + даты рождения — такой
     * пользователь никогда не смог бы подтвердить почту.
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->saveQuietly();
    }

    // ──────────────────────────────────────────────
    // Верификация телефона
    // ──────────────────────────────────────────────

    /** Телефон подтверждён кодом из SMS. */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /** Номер в виде 7XXXXXXXXXX (в БД он хранится в маске); null — номера нет. */
    public function normalizedPhone(): ?string
    {
        return PhoneNumber::normalize($this->phone);
    }

    /**
     * Отметить телефон подтверждённым.
     *
     * saveQuietly по той же причине, что и в markEmailAsVerified(): хук saving()
     * бросает ValidationException при дубле ФИО + даты рождения, и пользователь
     * с таким дублем никогда не смог бы подтвердить номер.
     */
    public function markPhoneAsVerified(): bool
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->saveQuietly();
    }

    // ──────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (self $user) {
            // Смена номера сбрасывает подтверждение: новый телефон
            // нужно подтвердить заново (аналогично смене e-mail).
            if ($user->exists && $user->isDirty('phone')) {
                $user->phone_verified_at = null;
            }

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
            // Запрещаем создание пользователя без отчества в поле name
            // (name имеет вид «Фамилия Имя Отчество» — минимум три слова).
            $nameParts = preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY);

            if (count($nameParts) < 3) {
                throw ValidationException::withMessages([
                    'name' => 'Отчество обязательно: укажите ФИО полностью (Фамилия Имя Отчество)',
                ]);
            }

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

    /**
     * Фамилия с инициалами, например «Иванов И. И.».
     * Генерируется из поля name (порядок: Фамилия Имя Отчество).
     */
    public function getShortNameAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($parts)) {
            return '';
        }

        $lastName = array_shift($parts);

        $initials = array_map(
            fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)).'.',
            $parts
        );

        return trim($lastName.' '.implode(' ', $initials));
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

    public function isAdmin(): bool
    {
        return $this->system_role === SystemRole::Admin;
    }

    public function isJudge(): bool
    {
        return $this->system_role === SystemRole::Judge;
    }

    public function isSecretary(): bool
    {
        return $this->system_role === SystemRole::Secretary;
    }

    public function isAccountant(): bool
    {
        return $this->system_role === SystemRole::Accountant;
    }

    /** Доступ к финансовому контуру: реестр платежей, подтверждение, журнал. */
    public function canManagePayments(): bool
    {
        return $this->system_role->canManagePayments();
    }

    /** Админ-разработчик: видит и правит только собственные регаты. */
    public function isDeveloperAdmin(): bool
    {
        return $this->system_role === SystemRole::DeveloperAdmin;
    }

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
        return $query->whereDoesntHave('teamMemberships', fn (Builder $q) => $q->where('status', 'active')
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
                if (! $this->birth_date) {
                    return null;
                }

                $today = Carbon::today();
                $next = $this->birth_date->copy()->setYear($today->year);

                if ($next->isPast() && ! $next->isToday()) {
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
                if (! $this->birth_date) {
                    return null;
                }

                $today = Carbon::today();
                $next = $this->birth_date->copy()->setYear($today->year);

                if ($next->isPast() && ! $next->isToday()) {
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

    /** Личные рейтинги по сезонам */
    public function personalRatings(): HasMany
    {
        return $this->hasMany(PersonalRating::class);
    }

    /** Новости, опубликованные пользователем */
    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'author_id');
    }

    /** Регаты, созданные пользователем */
    public function ownedRegattas(): HasMany
    {
        return $this->hasMany(Regatta::class, 'user_id');
    }

    /** Настройки уведомлений. Отсутствие строки для пары «категория+канал» = включено. */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /** Диалоги, в которых пользователь состоит участником (обращения в поддержку и переписки). */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['role', 'last_read_at'])
            ->withTimestamps();
    }

    /** Отправленные коды подтверждения телефона (последний — актуальный). */
    public function phoneVerificationCodes(): HasMany
    {
        return $this->hasMany(PhoneVerificationCode::class)->latest();
    }

    /** Привязанный чат с ботом в Telegram (если пользователь прошёл привязку) */
    public function telegramAccount(): HasOne
    {
        return $this->hasOne(TelegramAccount::class);
    }

    /** Telegram привязан и бот не заблокирован — уведомления доставимы. */
    public function hasLinkedTelegram(): bool
    {
        $account = $this->telegramAccount;

        return $account !== null && ! $account->isBlocked();
    }

    /**
     * Есть ли у пользователя доступ в админ-панель.
     * Какие именно разделы он там увидит — решает @see \App\Support\AccessControl.
     */
    public function canAccessAdminPanel(): bool
    {
        return $this->isAdmin()
            || $this->isJudge()
            || $this->isSecretary()
            || $this->isAccountant()
            || $this->isDeveloperAdmin();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->canAccessAdminPanel();
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
