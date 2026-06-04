<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Yacht extends Model implements HasMedia
{
    use HasFactory, HasUuids, SoftDeletes, InteractsWithMedia;

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

        'past_regattas',
    ];

    protected function casts(): array
    {
        return [
            'current_mass_kg' => 'decimal:2',
            'past_regattas'   => 'array',
            //'rent_price'      => 'decimal:2',
            //'for_rent'        => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Яхты, свободные в указанный период (не имеют одобренных заявок на регаты,
     * пересекающиеся с этим периодом).
     */
    public function scopeFreeDuring($query, string $dateStart, string $dateEnd)
    {
        return $query->whereDoesntHave('regattaEntries', function ($q) use ($dateStart, $dateEnd) {
            $q->where('status', 'approved')
              ->whereHas('regatta', function ($regattaQuery) use ($dateStart, $dateEnd) {
                  $regattaQuery->where('date_start', '<=', $dateEnd)
                               ->where('date_end', '>=', $dateStart);
              });
        });
    }
    public function prunable()
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now());
    }
    public function pruningScope(): Builder
    {
        // Удаляем записи, которые были "мягко удалены" более 7 дней назад
        return static::onlyTrashed()->where('deleted_at', '<=', now());
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
             ->useDisk('public');
    }

    public function registerMediaConversions(\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
             ->width(300)
             ->height(300)
             ->sharpen(10)
             ->nonQueued();
    }

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