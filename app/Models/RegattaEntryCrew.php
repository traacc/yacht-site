<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RegattaEntryCrew extends Pivot
{
    public $table = 'regatta_entry_crew';

    public $incrementing = true;

    protected $fillable = [
        'regatta_entry_id',
        'team_member_id',
        'role',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regattaEntry(): BelongsTo
    {
        return $this->belongsTo(RegattaEntry::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
