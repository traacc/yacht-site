<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentSettlement;
use App\Enums\PaymentStatus;
use App\Models\Concerns\NormalizesHeicImageColumns;
use App\Models\Scopes\OwnedScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Запись бухгалтерского реестра платежей.
 *
 * ВНИМАНИЕ: все изменения записываются в журнал (payment_registry_logs)
 * через PaymentRegistryLogObserver. Изменения, сделанные через
 * updateQuietly()/saveQuietly() или Query Builder, событий модели не порождают
 * и в журнал НЕ попадут — в таких местах логгер нужно вызывать явно
 * (@see \App\Observers\RegattaEntryFeeObserver).
 */
class PaymentRegistry extends Model
{
    use HasFactory, HasUuids, NormalizesHeicImageColumns, SoftDeletes;

    /** @var array<string> Колонки-пути (чек/документ), где heic нормализуется в webp. */
    protected array $heicImageColumns = ['document'];

    protected $fillable = [
        'name',
        'amount',
        'purpose',
        'payer_name',
        'status',
        'payment_method',
        'paid_at',
        'document',
        'payable_type',
        'payable_id',
        'regatta_id',
        'yacht_id',
        'team_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'purpose' => PaymentPurpose::class,
            'status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** Полиморфный источник платежа: RegattaEntry, Team и т.д. (может отсутствовать). */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Транзакции эквайринга (попытки онлайн-оплаты). */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /** Бухгалтер, подтвердивший фактический приход средств. */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** Кто последним изменил запись (null — изменение выполнила система). */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Журнал изменений записи. */
    public function logs(): HasMany
    {
        return $this->hasMany(PaymentRegistryLog::class);
    }

    /** Регата (денормализовано из заявки) — с удалёнными, чтобы не терять историю. */
    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class)->withTrashed();
    }

    /**
     * Яхта (денормализовано из заявки).
     *
     * Глобальный OwnedScope снимается обязательно: без этого яхты без владельца
     * (импорт из реестра ВФПС) вернут null — заголовки групп и экспорт опустеют.
     */
    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class)
            ->withoutGlobalScope(OwnedScope::class)
            ->withTrashed();
    }

    /** Команда (денормализовано из заявки или прямой привязки). */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class)->withTrashed();
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /** Бухгалтер подтвердил фактический приход средств. */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /** Форма расчёта (наличные/безнал); null — способ оплаты не указан. */
    public function settlement(): ?PaymentSettlement
    {
        return $this->payment_method?->settlement();
    }

    /** Кто последним изменил запись — для колонки реестра. */
    public function lastEditorLabel(): string
    {
        return $this->updatedBy?->name ?? 'Система';
    }

    /**
     * «От кого» для финансового отчёта: плательщик, а при пустом поле —
     * команда или источник платежа, чтобы строка отчёта не осталась безымянной.
     */
    public function payerLabel(): string
    {
        if (filled($this->payer_name)) {
            return (string) $this->payer_name;
        }

        return $this->team?->name
            ?? ($this->payableLabel() ?: 'Не указан');
    }

    /** Назначение платежа для колонок и заголовков групп. */
    public function purposeLabel(): string
    {
        return $this->purpose?->label() ?? 'Не указано';
    }

    /** Яхта: название и парусный номер. */
    public function yachtLabel(): string
    {
        $yacht = $this->yacht;

        if ($yacht === null) {
            return 'Без яхты';
        }

        return $yacht->vfps_number
            ? "{$yacht->name} ({$yacht->vfps_number})"
            : (string) $yacht->name;
    }

    public function regattaLabel(): string
    {
        return $this->regatta?->name ?? 'Без регаты';
    }

    public function teamLabel(): string
    {
        return $this->team?->name ?? 'Без команды';
    }

    /** Публичный URL прикреплённого документа. */
    public function getDocumentUrlAttribute(): ?string
    {
        return $this->document
            ? Storage::disk('public')->url($this->document)
            : null;
    }

    /** Команда, к которой относится платёж (напрямую или через заявку). */
    public function payableTeam(): ?Team
    {
        $payable = $this->payable;

        return match (true) {
            $payable instanceof Team => $payable,
            $payable instanceof RegattaEntry => $payable->team,
            default => null,
        };
    }

    /** Человекочитаемое описание связанной модели (источника платежа). */
    public function payableLabel(): string
    {
        $payable = $this->payable;

        if ($payable === null) {
            return '';
        }

        return match (true) {
            $payable instanceof RegattaEntry => 'Заявка: '
                .($payable->team?->name ?? '—')
                .' — '.($payable->regatta?->name ?? '—'),
            $payable instanceof Team => 'Команда: '.($payable->name ?? '—'),
            default => class_basename($payable).' #'.$payable->getKey(),
        };
    }
}
