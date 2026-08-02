<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdvertKind;
use App\Enums\AdvertPosition;
use App\Enums\AdvertType;
use App\Enums\SportCategory;
use App\Models\Advert;
use App\Models\AdvertCategory;
use App\Models\Regatta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Витрина доски объявлений: выборка, фильтры и справочники для формы фильтров.
 *
 * Фильтрация серверная, а не «весь набор в Alpine одним JSON», как на странице
 * яхт: там поиск работает только внутри текущей страницы, и для растущей доски
 * объявлений это неприемлемо.
 */
class AdvertBoard
{
    private const PER_PAGE = 24;

    /** Параметры GET, которые витрина принимает от формы фильтров. */
    public const FILTER_KEYS = [
        'q', 'kind', 'category', 'city', 'position', 'sport_category',
        'regatta', 'price_from', 'price_to', 'sort',
    ];

    /**
     * @param  array<string, mixed>  $filters  @see self::FILTER_KEYS
     * @return LengthAwarePaginator<Advert>
     */
    public function paginate(AdvertType $type, array $filters = []): LengthAwarePaginator
    {
        $query = Advert::query()
            ->ofType($type)
            ->visible()
            ->with(['category', 'yacht', 'media', 'regattas']);

        $this->applyFilters($query, $filters);
        $this->applySort($query, (string) ($filters['sort'] ?? ''));

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Счётчики видов для табов «Все / Предложения / Запросы».
     *
     * Пустой массив у досок без дуальности — табы тогда не рендерятся.
     *
     * @return array<string, int> value вида => количество
     */
    public function kindCounts(AdvertType $type): array
    {
        if ($type->kinds() === []) {
            return [];
        }

        $counts = Advert::query()
            ->ofType($type)
            ->visible()
            ->selectRaw('kind, count(*) as aggregate')
            ->groupBy('kind')
            ->pluck('aggregate', 'kind')
            ->all();

        return collect($type->kinds())
            ->mapWithKeys(fn (AdvertKind $kind): array => [
                $kind->value => (int) ($counts[$kind->value] ?? 0),
            ])
            ->all();
    }

    /**
     * Регаты, встречающиеся хотя бы в одном видимом объявлении доски.
     *
     * @return Collection<int, Regatta>
     */
    public function regattas(AdvertType $type): Collection
    {
        if (! $type->usesRegattas()) {
            return new Collection;
        }

        return Regatta::query()
            ->whereHas('adverts', fn (Builder $q) => $q->ofType($type)->visible())
            ->orderByDesc('date_start')
            ->get();
    }

    /**
     * Категории, в которых есть хотя бы одно видимое объявление: пустые пункты
     * в фильтре только мешают.
     *
     * @return Collection<int, AdvertCategory>
     */
    public function categories(AdvertType $type): Collection
    {
        if (! $type->usesCategories()) {
            return new Collection;
        }

        return AdvertCategory::query()
            ->ofType($type)
            ->whereHas('adverts', fn (Builder $q) => $q->visible())
            ->ordered()
            ->get();
    }

    /**
     * Города из опубликованных объявлений — для выпадающего списка фильтра.
     *
     * @return list<string>
     */
    public function cities(AdvertType $type): array
    {
        return Advert::query()
            ->ofType($type)
            ->visible()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->all();
    }

    /**
     * @param  Builder<Advert>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $query->when($search !== '', function (Builder $q) use ($search): void {
            $q->where(function (Builder $inner) use ($search): void {
                $inner->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        });

        // Enum'ы прогоняем через tryFrom: в query-строку может прийти что угодно,
        // а невалидное значение должно просто не фильтровать, а не ронять страницу.
        $kind = AdvertKind::tryFrom((string) ($filters['kind'] ?? ''));
        $position = AdvertPosition::tryFrom((string) ($filters['position'] ?? ''));
        $sportCategory = SportCategory::tryFrom((string) ($filters['sport_category'] ?? ''));

        $query
            ->when($kind !== null, fn (Builder $q) => $q->ofKind($kind))
            ->when($position !== null, fn (Builder $q) => $q->where('position', $position))
            ->when($sportCategory !== null, fn (Builder $q) => $q->where('sport_category', $sportCategory))
            ->when(filled($filters['category'] ?? null), fn (Builder $q) => $q->where('advert_category_id', $filters['category']))
            ->when(filled($filters['city'] ?? null), fn (Builder $q) => $q->where('city', $filters['city']))
            ->when(filled($filters['regatta'] ?? null), fn (Builder $q) => $q->whereHas(
                'regattas',
                fn (Builder $inner) => $inner->whereKey($filters['regatta']),
            ))
            ->when(is_numeric($filters['price_from'] ?? null), fn (Builder $q) => $q->where('price', '>=', (int) $filters['price_from']))
            ->when(is_numeric($filters['price_to'] ?? null), fn (Builder $q) => $q->where('price', '<=', (int) $filters['price_to']));
    }

    /** @param  Builder<Advert>  $query */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            // Объявления без цены («договорная») в ценовых сортировках уводим
            // в конец, чтобы они не занимали верх списка.
            'price_asc' => $query->orderByRaw('price is null')->orderBy('price'),
            'price_desc' => $query->orderByRaw('price is null')->orderByDesc('price'),
            'oldest' => $query->orderBy('published_at'),
            default => $query->orderByDesc('published_at'),
        };
    }

    /** @return array<string, string> */
    public static function sortOptions(): array
    {
        return [
            'newest' => 'Сначала новые',
            'oldest' => 'Сначала старые',
            'price_asc' => 'Сначала дешевле',
            'price_desc' => 'Сначала дороже',
        ];
    }
}
