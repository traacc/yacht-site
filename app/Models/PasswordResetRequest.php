<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Auth\SubmitPasswordResetRequestAction;
use App\Filament\Resources\PasswordResetRequests\PasswordResetRequestResource;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Заявка на восстановление пароля из формы входа на сайте.
 *
 * Пароль не восстанавливается автоматически: посетитель оставляет email и
 * телефон, администратор видит обращение в панели и либо отвечает текстом,
 * либо отправляет пользователю ссылку на смену пароля.
 *
 * @see SubmitPasswordResetRequestAction
 * @see PasswordResetRequestResource
 */
class PasswordResetRequest extends Model
{
    use HasUuids;

    protected $table = 'password_reset_requests';

    protected $fillable = [
        'user_id',
        'email',
        'phone',
        'answer',
        'answered_at',
        'answered_by',
        'reset_link_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'reset_link_sent_at' => 'datetime',
        ];
    }

    /**
     * Ищет пользователя, оставившего заявку.
     *
     * Сначала по email, затем по телефону: у авторегистрированных участников в
     * профиле технический адрес, а восстановить пароль они просят с настоящего —
     * по email их не найти, телефон же в профиле реальный. Номер сравниваем
     * нормализованным (в БД он в маске, у импортированных — в другом написании)
     * и только при единственном совпадении, чтобы не привязать заявку к чужому.
     */
    public static function matchUser(string $email, ?string $phone): ?User
    {
        $user = User::where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        $digits = PhoneNumber::normalize($phone);

        if ($digits === null) {
            return null;
        }

        $ids = User::query()
            ->whereNotNull('phone')
            ->pluck('phone', 'id')
            ->filter(static fn (?string $stored): bool => PhoneNumber::normalize($stored) === $digits)
            ->keys();

        return $ids->count() === 1 ? User::find($ids->first()) : null;
    }

    /** Пользователь, найденный при подаче заявки по email или телефону. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /** ФИО из профиля: в форме восстановления имя не спрашивают. */
    public function requesterName(): ?string
    {
        return $this->user?->name;
    }

    /**
     * Email заявки совпадает с email в профиле.
     *
     * Если заявитель найден по телефону, в профиле может быть другой (например,
     * технический) адрес — ссылку на смену пароля брокер отправит только на
     * адрес из профиля, поэтому до его исправления отправлять её бессмысленно.
     */
    public function emailMatchesUser(): bool
    {
        return $this->user !== null
            && mb_strtolower((string) $this->user->email) === mb_strtolower($this->email);
    }

    public function isAnswered(): bool
    {
        return filled($this->answer);
    }

    public function resetLinkSent(): bool
    {
        return $this->reset_link_sent_at !== null;
    }

    /** Обработана — админ либо ответил, либо отправил ссылку. */
    public function isProcessed(): bool
    {
        return $this->isAnswered() || $this->resetLinkSent();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('answer')->whereNull('reset_link_sent_at');
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNotNull('answer')->orWhereNotNull('reset_link_sent_at'));
    }
}
