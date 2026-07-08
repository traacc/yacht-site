<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegattaResultItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_result_id',
        'team_id',
        'yacht_id',
        // Денормализованный снапшот участника: сохраняется на случай удаления
        // команды/яхты и её заявки, чтобы итоговая строка результата уцелела.
        'team_name',
        'yacht_name',
        'sail_number',
        'captain_name',
        'crew_snapshot',
        'race_breakdown',
        'not_participate',
        'total_points',
        'final_position',
        'total_points_overridden',
        'final_position_overridden',
    ];

    protected function casts(): array
    {
        return [
            //'total_points'   => 'decimal:3',
            //'final_position' => 'integer',
            'total_points_overridden'   => 'boolean',
            'final_position_overridden' => 'boolean',
            'crew_snapshot'             => 'array',
            'race_breakdown'            => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regattaResult(): BelongsTo
    {
        return $this->belongsTo(RegattaResult::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    // ──────────────────────────────────────────────
    // Display accessors
    //
    // Отдают живую связь, а если команда/яхта удалены (или soft-deleted) —
    // денормализованный снапшот. Так публичная таблица результатов продолжает
    // показывать имя команды, яхту и парусный номер даже после удаления.
    // ──────────────────────────────────────────────

    public function getDisplayTeamNameAttribute(): ?string
    {
        return $this->team?->name ?? $this->team_name;
    }

    public function getDisplayYachtNameAttribute(): ?string
    {
        return $this->yacht?->name ?? $this->yacht_name;
    }

    public function getDisplaySailNumberAttribute(): ?string
    {
        return $this->yacht?->vfps_number ?? $this->sail_number;
    }
}