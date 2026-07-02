<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Конкретное значение опции яхты (например «Дакрон» для опции «Тип паруса»).
 */
class YachtOptionValue extends Model
{
    use HasUuids;

    protected $table = 'yacht_option_values';

    protected $fillable = [
        'yacht_option_id',
        'key',
        'label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => YachtOption::flushCache());
        static::deleted(fn () => YachtOption::flushCache());
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(YachtOption::class, 'yacht_option_id');
    }

    public function isUsed(): bool
    {
        return YachtOptionSelection::where('yacht_option_value_id', $this->id)->exists();
    }

    public function usageCount(): int
    {
        return YachtOptionSelection::where('yacht_option_value_id', $this->id)->count();
    }
}
