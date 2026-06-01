<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegattaResult extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_id',
        'result_type',
        'source',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'result_type' => 'string',
            'source'      => 'string',
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
        return $this->hasMany(RegattaResultItem::class)->orderBy('final_position');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPreliminary(): bool
    {
        return $this->result_type === 'preliminary';
    }

    public function isFinal(): bool
    {
        return $this->result_type === 'final';
    }
}