<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Заявка по кнопке «Хотите такой ремонт?» (раздел «Carter 30»).
 */
class RepairRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'repair_case_id',
        'user_id',
        'name',
        'phone',
        'email',
        'comment',
        'source',
        'processed_at',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function repairCase(): BelongsTo
    {
        return $this->belongsTo(RepairCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }

    public function isPending(): bool
    {
        return $this->processed_at === null;
    }

    /** Тема письма в отдел заказов: по ТЗ она должна называть конкретный кейс. */
    public function mailSubject(): string
    {
        $case = $this->repairCase?->title;

        return $case !== null && $case !== ''
            ? "Заявка на ремонт: {$case}"
            : 'Заявка на ремонт и модернизацию';
    }
}
