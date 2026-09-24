<?php

namespace App\Filament\Widgets;

use App\Enums\RegattaStatus;
use App\Filament\Resources\Regattas\RegattaResource;
use App\Models\Regatta;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingRegattas extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Ближайшие регаты';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            // Идущие сейчас и будущие регаты; сортировку задаём через defaultSort,
            // чтобы orderBy в запросе не перебивал сортировку по колонке «Дата».
            ->query(fn (): Builder => Regatta::query()
                ->visibleForUser()
                ->whereNotIn('regatta_status', [
                    RegattaStatus::Cancelled,
                    RegattaStatus::Postponed,
                    RegattaStatus::Finished,
                ])
                ->whereRaw('COALESCE(date_end, date_start) >= ?', [today()->toDateString()]))
            ->defaultSort('dateRange')

            ->columns([
                TextColumn::make('name')
                    ->searchable()->label('Название')
                    ->url(fn (Regatta $record): string => RegattaResource::getUrl('index'))->url(fn (Regatta $record): string => RegattaResource::getUrl('index', [
                        'tableAction' => 'edit',
                        'tableActionRecord' => $record->id,
                    ])),
                TextColumn::make('season.year')
                    ->searchable()->label('Сезон'),
                TextColumn::make('dateRange')
                    ->label('Дата')
                    ->getStateUsing(fn (Regatta $record): string => $record->dateRange())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('date_start', $direction)),
                TextColumn::make('water_area')
                    ->searchable()->label('Акватория')->columnSpanFull(),
            ])->emptyStateHeading('Пока нет ближайших регат')->stackedOnMobile()
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('view_all')
                    ->label('Все соревнования') // Текст ссылки
                    ->icon('heroicon-m-arrow-top-right-on-square') // Иконка рядом (по желанию)
                    ->color('gray') // Цвет (primary, gray, success и т.д.)
                    ->link(), // ТРАНСФОРМИРУЕТ кнопку в аккуратную текстовую ссылку
                // ->url(fn () => \App\Filament\Resources\Regattas\RegattaResource::getUrl('index')),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
