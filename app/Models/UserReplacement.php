<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись журнала замены пользователя: кого на кого заменили, кто и что изменилось.
 *
 * Записи создаёт только App\Actions\User\ReplaceUserAction — журнал
 * доступен исключительно на чтение (см. UserReplacementResource).
 *
 * @property array{
 *     entries: list<array{regatta: string, date: ?string, team: ?string, role: string, merged: bool}>,
 *     submitted: int, teams: list<array{team: string, merged: bool}>,
 *     snapshots: int, join_requests: int, invitations: int,
 *     seasons: list<string>, blockers: list<string>
 * } $summary
 */
class UserReplacement extends Model
{
    use HasUuids;

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'from_name',
        'to_name',
        'from_deleted',
        'summary',
        'performed_by',
        'performed_by_name',
    ];

    protected function casts(): array
    {
        return [
            'from_deleted' => 'boolean',
            'summary' => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id')->withTrashed();
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id')->withTrashed();
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by')->withTrashed();
    }
}
