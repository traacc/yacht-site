<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YachtRental extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'yacht_id',
        'regatta_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class);
    }
}
