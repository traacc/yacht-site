<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\NormalizesHeicImageColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class PaymentRegistry extends Model
{
    use HasFactory, HasUuids, NormalizesHeicImageColumns;

    /** @var array<string> Колонки-пути (чек/документ), где heic нормализуется в webp. */
    protected array $heicImageColumns = ['document'];

    protected $fillable = [
        'name',
        'amount',
        'status',
        'payment_method',
        'paid_at',
        'document',
        'payable_type',
        'payable_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
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

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

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
