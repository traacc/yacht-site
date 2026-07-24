<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaceResult extends Model
{
    use HasFactory, HasUuids;

    /**
     * Коды судейских пенальти (парусный спорт, правила ISAF).
     * DNF — не финишировал, DNS — не стартовал, DSQ — дисквалификация,
     * OCS — за линией старта, RET — отказался от гонки.
     */
    public const PENALTY_CODES = ['DNF', 'DNS', 'DSQ', 'OCS', 'RET', 'BFD', 'UFD'];

    protected $fillable = [
        'event_id',
        'regatta_entry_id',
        'position',
        'points',
        'is_discarded',
        'penalty_code',
    ];

    protected function casts(): array
    {
        return [
            'is_discarded' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function race(): BelongsTo
    {
        return $this->belongsTo(RegattaEvents::class, 'event_id');
    }

    public function regattaEntry(): BelongsTo
    {
        return $this->belongsTo(RegattaEntry::class, 'regatta_entry_id');
    }
    /*
    public function regattaResultItem(): BelongsTo
    {
        return $this->belongsTo(RegattaResultItem::class, 'regatta_result_items_id');
    }
    */
    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function hasPenalty(): bool
    {
        return $this->penalty_code !== null;
    }

    /**
     * Выброшен ли результат из зачёта: флаг авторасчёта (выброс худших
     * результатов по настройке регаты) или скобки в очках — так помечают
     * выброс судейские протоколы (.rgd / внешний API).
     */
    public function isDiscarded(): bool
    {
        $points = trim((string) $this->points);

        return $this->is_discarded
            || (str_starts_with($points, '(') && str_ends_with($points, ')'));
    }

    public function getDisplayPositionAttribute(): string
    {
        return $this->penalty_code ?? (string) $this->position;
    }
}
