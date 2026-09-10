<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Auth\SubmitPasswordResetRequestAction;
use App\Filament\Resources\PasswordResetRequests\PasswordResetRequestResource;
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

    /** Пользователь с таким email, если он нашёлся при подаче заявки. */
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
