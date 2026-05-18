<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'album_id',
        'type',
        'url',
        'thumbnail_url',
        'original_filename',
        'file_size_bytes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'sort_order'      => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPhoto(): bool { return $this->type === 'photo'; }
    public function isVideo(): bool { return $this->type === 'video'; }

    public function getFileSizeForHumansAttribute(): string
    {
        if ($this->file_size_bytes === null) return '—';
        $kb = $this->file_size_bytes / 1024;
        if ($kb < 1024) return round($kb, 1) . ' KB';
        return round($kb / 1024, 1) . ' MB';
    }
}