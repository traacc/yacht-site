<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ServiceSubject;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Заявка из раздела «Услуги» (ТЗ 3-го этапа, п. 7).
 *
 * Одна модель на все подразделы: тип задаёт ServiceType, специфичные поля
 * лежат в `payload` по описанию ServiceType::payloadFields().
 */
class ServiceRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'status',
        'user_id',
        'subject_type',
        'subject_id',
        'name',
        'phone',
        'email',
        'comment',
        'date_start',
        'date_end',
        'quantity',
        'payload',
        'payment_registry_id',
        'source',
        'admin_comment',
        'processed_at',
        'processed_by',
    ];

    protected $attributes = [
        'status' => ServiceRequestStatus::New->value,
    ];

    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'status' => ServiceRequestStatus::class,
            'date_start' => 'date',
            'date_end' => 'date',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Связи
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** Объект заявки: яхта, тур, зарубежная регата, сертификат. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Задел под оплату услуг; эквайринг ещё не подключён (ТЗ п. 4.1). */
    public function paymentRegistry(): BelongsTo
    {
        return $this->belongsTo(PaymentRegistry::class);
    }

    // ──────────────────────────────────────────────
    // Скоупы
    // ──────────────────────────────────────────────

    public function scopeOfType(Builder $query, ServiceType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /** Необработанные заявки — их считает бейдж ресурса и плитка дашборда. */
    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', ServiceRequestStatus::New->value);
    }

    public function isNew(): bool
    {
        return $this->status === ServiceRequestStatus::New;
    }

    // ──────────────────────────────────────────────
    // Представление
    // ──────────────────────────────────────────────

    /**
     * Тема письма в отдел заказов.
     *
     * По ТЗ письмо обязано называть услугу; даты и количество добавлены,
     * чтобы заявку можно было опознать прямо в списке писем.
     */
    public function mailSubject(): string
    {
        $subject = 'Заявка на услугу: '.$this->type->label();

        $details = array_filter([
            $this->dateRangeLabel(),
            $this->quantityLabel(),
        ]);

        return $details === []
            ? $subject
            : $subject.' ('.implode(', ', $details).')';
    }

    /**
     * Подпись объекта заявки — конкретного похода, регаты, сертификата.
     *
     * Шаблоны и админка спрашивают заявку, а не её subject: типы объектов
     * будут добавляться, а вызовы останутся прежними.
     */
    public function subjectLabel(): ?string
    {
        return $this->subject instanceof ServiceSubject
            ? $this->subject->subjectLabel()
            : null;
    }

    public function subjectUrl(): ?string
    {
        return $this->subject instanceof ServiceSubject
            ? $this->subject->subjectUrl()
            : null;
    }

    public function dateRangeLabel(): ?string
    {
        if ($this->date_start === null && $this->date_end === null) {
            return null;
        }

        if ($this->date_end === null) {
            return $this->date_start->format('d.m.Y');
        }

        if ($this->date_start === null) {
            return 'до '.$this->date_end->format('d.m.Y');
        }

        if ($this->date_start->isSameDay($this->date_end)) {
            return $this->date_start->format('d.m.Y');
        }

        return $this->date_start->format('d.m.Y').' — '.$this->date_end->format('d.m.Y');
    }

    /** «4 яхты», «2 человека» — количество вместе с единицей подраздела. */
    public function quantityLabel(): ?string
    {
        if ($this->quantity === null) {
            return null;
        }

        return $this->type->quantityWithUnit($this->quantity);
    }

    /**
     * Содержимое payload в человекочитаемом виде.
     *
     * Единственное место, где json превращается в подписи: используется и в
     * письме, и в infolist админки.
     *
     * @return array<string, string> подпись поля => значение
     */
    public function payloadLabels(): array
    {
        $payload = $this->payload ?? [];
        $labels = [];

        foreach ($this->type->payloadFields() as $key => $field) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            $formatted = match ($field['type']) {
                'select' => $field['options'][$value] ?? (string) $value,
                'checkbox' => $value ? 'Да' : 'Нет',
                default => trim((string) $value),
            };

            if ($formatted === '') {
                continue;
            }

            $labels[$field['label']] = $formatted;
        }

        return $labels;
    }
}
