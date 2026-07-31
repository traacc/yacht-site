<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CharterPriceUnit;
use App\Enums\CharterYachtStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Чартерная яхта под зарубежную регату (ТЗ 3-го этапа, п. 7).
 *
 * К реестру `yachts` отношения не имеет: лодка берётся в чартер за границей,
 * владельца и документов на сайте у неё нет.
 */
class ForeignRegattaYacht extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'foreign_regatta_id',
        'model',
        'name',
        'year',
        'price',
        'price_unit',
        'price_note',
        'status',
        'sort_order',
    ];

    protected $attributes = [
        'status' => CharterYachtStatus::Free->value,
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'price' => 'integer',
            'price_unit' => CharterPriceUnit::class,
            'status' => CharterYachtStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(ForeignRegatta::class, 'foreign_regatta_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', CharterYachtStatus::Free->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('model');
    }

    public function isAvailable(): bool
    {
        return $this->status->isAvailable();
    }

    /** «Bavaria 46 «Nika», 2018» — подпись для витрины и выпадающего списка заявки. */
    public function title(): string
    {
        $title = trim((string) $this->model);

        $name = trim((string) $this->name);
        if ($name !== '') {
            $title .= ' «'.$name.'»';
        }

        return $this->year === null ? $title : $title.', '.$this->year;
    }

    public function priceLabel(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        $label = number_format((float) $this->price, 0, ',', ' ').' ₽';

        return $this->price_unit === null
            ? $label
            : $label.' '.$this->price_unit->label();
    }
}
