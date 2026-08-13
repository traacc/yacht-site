<?php

namespace App\Models;

use App\Enums\ParticipationKind;
use App\Enums\RegattaEntrySource;
use App\Enums\TeamMemberRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Hash;

class RegattaEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_id',
        'team_id',
        'yacht_id',
        'participation_kind',
        'user_id',
        'status',
        'source',
        'documents_complete',
        'fee_paid',
        'submitted_at',
        'entry_password',
        'open_for_join',
        'join_conditions',
        'join_contact_email',
    ];

    protected $hidden = [
        'entry_password',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'entry_password' => 'hashed',
            'documents_complete' => 'boolean',
            'fee_paid' => 'boolean',
            'source' => RegattaEntrySource::class,
            'participation_kind' => ParticipationKind::class,
            'open_for_join' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    /**
     * Автор заявки: получатель уведомлений в ЛК.
     *
     * Nullable — у заявок, заведённых админом или импортом, автора нет;
     * для таких заявок адресатом остаётся капитан команды.
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Отклики «Хочу в этот экипаж», свежие сверху. */
    public function crewJoinRequests(): HasMany
    {
        return $this->hasMany(CrewJoinRequest::class)->latest();
    }

    /** Экипаж заявки: участники команды с ролями (captain / main / reserve), капитан всегда первым */
    public function crew(): HasMany
    {
        return $this->hasMany(RegattaEntryCrew::class)
            ->orderByRaw("FIELD(`role`, 'captain', 'main', 'reserve')")
            ->orderBy('id');
    }

    /** Результаты этой заявки по отдельным гонкам */
    public function raceResults(): HasMany
    {
        return $this->hasMany(RaceResult::class)->orderBy('event_id');
    }

    /** Документы заявки (ORC-сертификаты, страховка и т.д.) */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Записи в реестре платежей, связанные с этой заявкой (сборы за участие) */
    public function paymentRegistries(): MorphMany
    {
        return $this->morphMany(PaymentRegistry::class, 'payable');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /** Заявка одного человека (регулярные и выездные регаты), без команды. */
    public function isIndividual(): bool
    {
        return $this->participation_kind === ParticipationKind::Individual;
    }

    /**
     * Открыт ли экипаж для добора людей со стороны.
     *
     * Кнопка «Хочу в этот экипаж» показывается только у действующих заявок
     * на регату, куда ещё можно заявиться.
     */
    public function isOpenForJoin(): bool
    {
        return $this->open_for_join
            && in_array($this->status, ['pending', 'approved'], true)
            && (bool) $this->regatta?->isOpenForRegistration();
    }

    /**
     * Кому уходит уведомление о заявке в ЛК: автору заявки, а у заявок без
     * автора (админ, импорт) — организатору команды.
     */
    public function notifiableUser(): ?User
    {
        if ($this->applicant) {
            return $this->applicant;
        }

        return $this->team?->activeMembers()
            ->wherePivot('role', TeamMemberRole::Organizer->value)
            ->first();
    }

    /** Почта, указанная экипажем для откликов; иначе — почта автора заявки. */
    public function joinNotificationEmail(): ?string
    {
        return $this->join_contact_email ?: $this->notifiableUser()?->email;
    }

    /** Поданы ли не все обязательные документы (заявка помечена для проверки) */
    public function hasMissingDocuments(): bool
    {
        return ! $this->documents_complete;
    }

    /** Задан ли спец-пароль заявки (для редактирования без входа) */
    public function hasEntryPassword(): bool
    {
        return filled($this->entry_password);
    }

    /** Проверить спец-пароль заявки */
    public function checkEntryPassword(string $plain): bool
    {
        return $this->entry_password !== null
            && Hash::check($plain, $this->entry_password);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isWithdrawn(): bool
    {
        return $this->status === 'withdrawn';
    }

    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'submitted_at' => $this->submitted_at ?? now(),
        ]);
    }

    public function reject(): void
    {
        $this->update(['status' => 'rejected']);
    }

    public function withdraw(): void
    {
        $this->update(['status' => 'withdrawn']);
    }

    /** Суммарные очки по всем гонкам этой заявки */
    public function totalPoints(): float
    {
        return (float) $this->raceResults()->sum('points');
    }
}
