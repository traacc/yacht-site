<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Season;
use App\Services\RatingCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Кнопка форсированного пересчёта рейтингов сезона.
 *
 * Пересчёт полностью перестраивает командный и личный рейтинг выбранного
 * сезона (RatingCalculator::recalculateForSeason) из текущих итоговых строк
 * результатов и актуального состава экипажей. Нужна, когда рейтинги «разъехались»
 * с данными — например, после ручных правок истории или импорта.
 *
 * Пересчёт идемпотентен и не трогает сами результаты гонок, только рейтинговые
 * таблицы (team_ratings, personal_ratings).
 */
trait RecalculatesRatings
{
    protected function recalculateRatingsAction(): Action
    {
        return Action::make('recalculate_ratings')
            ->label('Пересчитать рейтинги')
            ->icon(Heroicon::ArrowPath)
            ->color('white')
            ->modalHeading('Пересчёт рейтингов')
            ->modalDescription('Командный и личный рейтинг выбранного сезона будут пересчитаны заново по текущим результатам и составам экипажей. Результаты гонок не изменятся.')
            ->modalSubmitActionLabel('Пересчитать')
            ->form([
                Checkbox::make('all_seasons')
                    ->label('Все сезоны')
                    ->live()
                    ->default(false),

                Select::make('season_id')
                    ->label('Сезон')
                    ->options(fn (): array => Season::query()
                        ->orderByDesc('year')
                        ->pluck('year', 'id')
                        ->all())
                    ->default(fn (): ?string => Season::current()?->id)
                    ->searchable()
                    ->hidden(fn (Get $get): bool => (bool) $get('all_seasons'))
                    ->required(fn (Get $get): bool => ! $get('all_seasons')),
            ])
            ->action(function (array $data): void {
                $calculator = app(RatingCalculator::class);

                if ($data['all_seasons'] ?? false) {
                    $seasons = Season::all();

                    if ($seasons->isEmpty()) {
                        Notification::make()
                            ->title('Сезоны не найдены')
                            ->warning()
                            ->send();

                        return;
                    }

                    $seasons->each(fn (Season $season) => $calculator->recalculateForSeason($season));

                    Notification::make()
                        ->title('Рейтинги пересчитаны')
                        ->body('Обновлено сезонов: ' . $seasons->count() . '.')
                        ->success()
                        ->send();

                    return;
                }

                $season = Season::find($data['season_id']);

                if ($season === null) {
                    Notification::make()
                        ->title('Сезон не найден')
                        ->danger()
                        ->send();

                    return;
                }

                $calculator->recalculateForSeason($season);

                Notification::make()
                    ->title('Рейтинги пересчитаны')
                    ->body('Сезон ' . $season->year . ' обновлён.')
                    ->success()
                    ->send();
            });
    }
}
