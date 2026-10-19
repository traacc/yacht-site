<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Services\SeasonContext;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Фильтр таблицы по сезону.
 *
 * По умолчанию выбран текущий сезон (SeasonContext::current()), чтобы записи
 * разных лет не смешивались в одном списке; «Все» остаётся доступным через
 * сброс значения. В списке — только сезоны с данными.
 *
 * Таблицам с этим фильтром стоит включать persistFiltersInSession(): выбранный
 * сезон тогда не сбрасывается при переходах по админке.
 */
trait FiltersBySeason
{
    /**
     * @param  string|null  $relationship  Путь до модели с season_id (например,
     *                                     'regatta' или 'regattaEntry.regatta');
     *                                     null — колонка season_id у самой записи.
     * @param  bool  $defaultToCurrent  false — для списков, которые сами ограничены
     *                                  незавершёнными регатами: там встречаются заявки
     *                                  следующего сезона, их нельзя прятать по умолчанию.
     */
    protected static function seasonFilter(?string $relationship = null, bool $defaultToCurrent = true): SelectFilter
    {
        return SelectFilter::make('season')
            ->label('Сезон')
            ->options(fn (): array => app(SeasonContext::class)->idOptions())
            ->default(fn (): ?string => $defaultToCurrent ? app(SeasonContext::class)->current()?->id : null)
            ->query(function (Builder $query, array $data) use ($relationship): Builder {
                if (blank($data['value'])) {
                    return $query;
                }

                if ($relationship === null) {
                    return $query->where($query->qualifyColumn('season_id'), $data['value']);
                }

                return $query->whereHas(
                    $relationship,
                    fn (Builder $related) => $related->where('season_id', $data['value']),
                );
            });
    }
}
