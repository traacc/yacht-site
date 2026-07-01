<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Мероприятие расписания регаты (регистрация, открытие, брифинг и т.п.).
 *
 * В отличие от гонок ({@see RegattaEvents}) не имеет результатов.
 */
class RegattaScheduleEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_id',
        'name',
        'description',
        'event_datetime',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'event_datetime' => 'datetime',
            'sort_order'     => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class);
    }
}
