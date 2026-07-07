<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TeamMemberInvitationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TeamMemberInvitation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'team_id',
        'user_id',
        'from_team_id',
        'requested_by',
        'status',
        'message',
        'rejection_reason',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => TeamMemberInvitationStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** Команда, в которую приглашают (новая главная команда) */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /** Текущая главная команда участника (снимок на момент запроса) */
    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    /** Приглашаемый участник */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Капитан, отправивший запрос */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === TeamMemberInvitationStatus::Pending;
    }

    /**
     * Одобрить запрос: сделать целевую команду новой главной командой участника.
     *
     * - Снимает признак постоянного участника со всех команд пользователя.
     * - Делает пользователя активным постоянным участником целевой команды.
     * - Отклоняет прочие ожидающие приглашения этого пользователя.
     */
    public function approve(): void
    {
        DB::transaction(function (): void {
            // Снять «постоянный участник» со старой главной команды и пометить,
            // что участник её покинул (статус «left»). Целевую команду не трогаем —
            // ниже она станет активной главной командой.
            TeamMember::query()
                ->where('user_id', $this->user_id)
                ->where('is_permanent', true)
                ->where('team_id', '!=', $this->team_id)
                ->update([
                    'is_permanent' => false,
                    'status'       => 'left',
                ]);

            // Сделать участника активным постоянным членом целевой команды
            TeamMember::updateOrCreate(
                ['team_id' => $this->team_id, 'user_id' => $this->user_id],
                [
                    'status'       => 'active',
                    'is_permanent' => true,
                    'joined_at'    => now(),
                ],
            );

            $this->update([
                'status'       => TeamMemberInvitationStatus::Approved,
                'responded_at' => now(),
            ]);

            // Прочие ожидающие приглашения этого участника теряют смысл
            static::query()
                ->where('user_id', $this->user_id)
                ->whereKeyNot($this->getKey())
                ->where('status', TeamMemberInvitationStatus::Pending->value)
                ->update([
                    'status'       => TeamMemberInvitationStatus::Rejected->value,
                    'responded_at' => now(),
                ]);
        });
    }

    /**
     * Отклонить запрос.
     */
    public function reject(?string $reason = null): void
    {
        $this->update([
            'status'           => TeamMemberInvitationStatus::Rejected,
            'rejection_reason' => $reason,
            'responded_at'     => now(),
        ]);
    }
}
