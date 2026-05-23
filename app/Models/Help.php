<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Help extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'help';

    protected $fillable = [
        'help_category_id',
        'title',
        'desc',
        'includes',
        'contact_type',
        'specialist_name',
        'specialist_email',
        'specialist_phone',
        'specialist_sphere',
        'specialist_city',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'includes' => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSpecialist(Builder $query): Builder
    {
        return $query->where('contact_type', 'specialist');
    }

    public function scopeManager(Builder $query): Builder
    {
        return $query->where('contact_type', 'manager');
    }
}
