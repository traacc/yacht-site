<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasFactory, HasUuids;

    /**
     * Типы документов:
     * regulation          — положение о регате
     * race_instructions   — инструкция по гонкам
     * charter             — устав (для организации / команды)
     * protocol            — протокол результатов
     * orc_certificate     — ORC-сертификат яхты
     * other               — прочее
     */
    public const TYPES = [
        'regulation',
        'race_instructions',
        'charter',
        'protocol',
        'orc_certificate',
        'other',
    ];

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'doc_type',
        'title',
        'url',
        'file_size_bytes',
        'mime_type',
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

    /** Полиморфный родитель: Regatta, Yacht, Team и т.д. */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function getFileSizeForHumansAttribute(): string
    {
        if ($this->file_size_bytes === null) return '—';
        $kb = $this->file_size_bytes / 1024;
        if ($kb < 1024) return round($kb, 1) . ' KB';
        return round($kb / 1024, 1) . ' MB';
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}