<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Вопрос зарегистрированного пользователя администрации.
 *
 * Пользователь задаёт вопрос через модальное окно на главной странице,
 * администратор отвечает в админ-панели и при необходимости переносит
 * пару «вопрос/ответ» в общий FAQ.
 *
 * @see \App\Models\Faq
 */
class UserQuestion extends Model
{
    use HasUuids;

    protected $table = 'user_questions';

    protected $fillable = [
        'user_id',
        'question',
        'answer',
        'answered_at',
        'answered_by',
        'imported_to_faq',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'imported_to_faq' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function isAnswered(): bool
    {
        return filled($this->answer);
    }

    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->whereNull('answer');
    }

    public function scopeAnswered(Builder $query): Builder
    {
        return $query->whereNotNull('answer');
    }
}
