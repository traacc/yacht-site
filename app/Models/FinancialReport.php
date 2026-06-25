<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FinancialReport extends Model
{
    use HasFactory, HasUuids;

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
