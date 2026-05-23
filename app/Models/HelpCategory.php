<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HelpCategory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'help_category';

    protected $fillable = [
        'title',
        'slug',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function helps(): HasMany
    {
        return $this->hasMany(Help::class, 'help_category_id');
    }
}
