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
        'date_start',
        'date_end',
        'price_event',
        'price_pro',
    ];

    protected function casts(): array
    {
        return [
            'date_start'  => 'date',
            'date_end'    => 'date',
            'price_event' => 'decimal:2',
            'price_pro'   => 'decimal:2',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}
