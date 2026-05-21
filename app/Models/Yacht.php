<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Yacht extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'vfps_number',
        'user_id',
        'gims_number',
        'orc_cert_url',
        'class',
        'project',
        'year',
        'reg_place',
        'sail_type',
        'current_mass_kg',
        'for_rent',
        'rent_price',
        'approval_status',

        'owner_name',
        'owner_email',
        'owner_phone',
        'owner_photo',
    ];

    protected function casts(): array
    {
        return [
            'current_mass_kg' => 'decimal:2',
            //'rent_price'      => 'decimal:2',
            //'for_rent'        => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function regattaEntries(): HasMany
    {
        return $this->hasMany(RegattaEntry::class);
    }

    /** Документы (ORC-сертификаты, технические паспорта) */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isApproved(): bool { return $this->approval_status === 'approved'; }
    public function isPending(): bool  { return $this->approval_status === 'pending'; }

    /**
     * Проверить занятость яхты в указанный период.
     * Учитываются только одобренные заявки.
     */
    public function isBusyDuring(string $dateStart, string $dateEnd): bool
    {
        return $this->regattaEntries()
                    ->where('status', 'approved')
                    ->whereHas('regatta', function ($q) use ($dateStart, $dateEnd) {
                        $q->where('date_start', '<=', $dateEnd)
                          ->where('date_end', '>=', $dateStart);
                    })
                    ->exists();
    }
}