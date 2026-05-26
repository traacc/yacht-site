<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

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
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Область видимости: документы определённого типа.
     */
    public function scopeOfType($query, string $docType)
    {
        return $query->where('doc_type', $docType);
    }

    // ──────────────────────────────────────────────
    // Static helpers
    // ──────────────────────────────────────────────

    /**
     * Сгруппировать документы по doc_type и вернуть массив url-ов.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, self> $documents
     * @return array<string, string[]>  doc_type => [url, url, …]
     */
    public static function groupUrlsByType($documents): array
    {
        return $documents
            ->groupBy('doc_type')
            ->map(fn (Collection $group) => $group->pluck('url')->filter(fn ($u) => $u !== '')->values()->toArray())
            ->toArray();
    }

    /**
     * Проверить, есть ли хотя бы один загруженный файл для заданного doc_type.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, self> $documents
     */
    public static function hasFileForType($documents, string $docType): bool
    {
        return $documents
            ->where('doc_type', $docType)
            ->filter(fn (self $doc) => $doc->url !== '' && $doc->url !== null)
            ->isNotEmpty();
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function getFileUrlAttribute(): string
    {
        return $this->url ? Storage::disk('public')->url($this->url) : '#';
    }

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
