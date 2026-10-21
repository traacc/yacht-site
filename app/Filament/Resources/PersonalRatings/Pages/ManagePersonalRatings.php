<?php

namespace App\Filament\Resources\PersonalRatings\Pages;

use App\Actions\Rating\GenerateSeasonParticipantsPdfAction;
use App\Enums\ParticipantSort;
use App\Filament\Concerns\RecalculatesRatings;
use App\Filament\Resources\PersonalRatings\PersonalRatingResource;
use App\Models\Season;
use App\Services\RatingCalculator;
use App\Services\SeasonContext;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;

class ManagePersonalRatings extends ManageRecords
{
    use RecalculatesRatings;

    protected static string $resource = PersonalRatingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->createAnother(false)->modalHeading('Новый личный рейтинг'),
            $this->recalculateRatingsAction(),
            $this->seasonParticipantsAction(),
        ];
    }

    /**
     * Участники сезона: таблица «человек × регата» с очками, суммой и местом,
     * сортировка на выбор и выгрузка того же списка в PDF.
     */
    private function seasonParticipantsAction(): Action
    {
        return Action::make('seasonParticipants')
            ->label('Участники сезона')
            ->icon('heroicon-o-users')
            ->color('white')
            ->modalHeading('Участники сезона')
            ->modalWidth(Width::Full)
            ->fillForm(fn (): array => [
                'season_id' => $this->tableFilters['season']['value'] ?? app(SeasonContext::class)->current()?->id,
                'sort' => ParticipantSort::Name->value,
            ])
            ->schema([
                Grid::make(2)->schema([
                    Select::make('season_id')
                        ->label('Сезон')
                        ->options(fn (): array => app(SeasonContext::class)->idOptions())
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    Select::make('sort')
                        ->label('Сортировка')
                        ->options(ParticipantSort::options())
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                ]),
                View::make('filament.resources.participants-table')
                    ->viewData(function (Get $get): array {
                        $season = Season::find($get('season_id'));
                        $table = $season
                            ? app(RatingCalculator::class)->seasonPersonalTable($season)
                            : ['regattas' => [], 'rows' => []];
                        $sort = ParticipantSort::tryFrom((string) $get('sort')) ?? ParticipantSort::Name;

                        return [
                            'regattas' => $table['regattas'],
                            'rows' => $sort->apply($table['rows']),
                        ];
                    }),
            ])
            ->modalSubmitActionLabel('Скачать PDF')
            ->modalCancelActionLabel('Закрыть')
            ->action(fn (array $data) => app(GenerateSeasonParticipantsPdfAction::class)->execute(
                Season::findOrFail($data['season_id']),
                ParticipantSort::from($data['sort']),
            ));
    }
}
