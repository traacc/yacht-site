<?php

namespace App\Models;

use App\Models\Concerns\RegistersResponsiveFormats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Help extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

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
        'specialist_site',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'includes' => 'array',
            'specialist_phone' => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addResponsiveFormatConversions();
    }

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

    public function pruningScope(): Builder
    {
        // Удаляем записи, которые были "мягко удалены" более 7 дней назад
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(7));
    }
}
