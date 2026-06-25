<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RegattaEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_id',
        'team_id',
        'yacht_id',
        'status',
        'documents_complete',
        'fee_paid',
        'submitted_at',
        'entry_password',
    ];

    protected $hidden = [
        'entry_password',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at'       => 'datetime',
            'entry_password'     => 'hashed',
            'documents_complete' => 'boolean',
            'fee_paid'           => 'boolean',
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

    /** Экипаж заявки: участники команды с ролями (main / reserve) */
    public function crew(): HasMany
    {
        return $this->hasMany(RegattaEntryCrew::class);
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
            && \Illuminate\Support\Facades\Hash::check($plain, $this->entry_password);
    }

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isApproved(): bool  { return $this->status === 'approved'; }
    public function isRejected(): bool  { return $this->status === 'rejected'; }
    public function isWithdrawn(): bool { return $this->status === 'withdrawn'; }

    public function approve(): void
    {
        $this->update([
            'status'       => 'approved',
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