<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Выбранное на яхте значение опции. Таблица yacht_option_selections
 * является одновременно pivot-таблицей (Yacht::optionValues()) и
 * самостоятельной моделью для запросов по опции/значению.
 *
 * Композитный первичный ключ (yacht_id, yacht_option_id) гарантирует,
 * что на одну опцию у яхты выбрано не более одного значения.
 */
class YachtOptionSelection extends Model
{
    protected $table = 'yacht_option_selections';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'yacht_id',
        'yacht_option_id',
        'yacht_option_value_id',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(YachtOption::class, 'yacht_option_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(YachtOptionValue::class, 'yacht_option_value_id');
    }
}
