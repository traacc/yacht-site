<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class RegattaResult extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_id',
        'result_type',
        'source',
        'pdf_path',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'result_type' => 'string',
            'source' => 'string',
            'is_published' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class);
    }

    public function items(): HasMany
    {
        // final_position — строковая колонка (может быть пустой/нечисловой),
        // поэтому сортируем как число: пустые/NULL в конец, остальные по возрастанию.
        return $this->hasMany(RegattaResultItem::class)
            ->orderByRaw("(final_position IS NULL OR final_position = ''), CAST(final_position AS UNSIGNED)");
    }

    /**
     * Заявки на ту же регату — через общий regatta_id.
     * Нужны релейшен-менеджеру заявок на странице редактирования результата:
     * состав участников результата формируется именно по ним.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(RegattaEntry::class, 'regatta_id', 'regatta_id');
    }

    public function regattaEntry(): HasOne
    {
        return $this->hasOne(RegattaEntry::class, 'regatta_id', 'regatta_id')
            ->where('team_id', $this->team_id)    // dynamic – see note below
            ->where('yacht_id', $this->yacht_id);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? Storage::disk('public')->url($this->pdf_path) : null;
    }

    public function isPreliminary(): bool
    {
        return $this->result_type === 'preliminary';
    }

    public function isFinal(): bool
    {
        return $this->result_type === 'final';
    }
}
