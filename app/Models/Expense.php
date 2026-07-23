<?php

namespace App\Models;

use App\Models\Concerns\NormalizesHeicImageColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    use HasFactory, HasUuids, NormalizesHeicImageColumns;

    /** @var array<string> Колонки-пути (чек/документ), где heic нормализуется в webp. */
    protected array $heicImageColumns = ['document'];

    protected $fillable = [
        'name',
        'document',
    ];

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /** Публичный URL прикреплённого документа. */
    public function getDocumentUrlAttribute(): ?string
    {
        return $this->document
            ? Storage::disk('public')->url($this->document)
            : null;
    }
}
