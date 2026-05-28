<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

use App\Models\Regatta;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

use Filament\Actions\Action;

class UpcomingRegattas extends TableWidget
{
    protected static ?string $heading = 'Ближайшие регаты';
    protected int | string | array $columnSpan = 'full';
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Regatta::query())

            ->columns([
                TextColumn::make('name')
                    ->searchable()->label('Название')
                    ->url(fn (Regatta $record): string => \App\Filament\Resources\Regattas\RegattaResource::getUrl('index'))->url(fn (Regatta $record): string => \App\Filament\Resources\Regattas\RegattaResource::getUrl('index', [
                    'tableAction'       => 'edit',
                    'tableActionRecord' => $record->id,
                ])),
                TextColumn::make('season.year')
                    ->searchable()->label('Сезон'),
                TextColumn::make('dateRange')
                    ->label('Дата')
                    ->getStateUsing(fn (Regatta $record): string => $record->dateRange())
                    ->sortable(query: fn (Builder $q, string $dir) => $q->orderBy('date_start', $dir)),
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
                    //->url(fn () => \App\Filament\Resources\Regattas\RegattaResource::getUrl('index')), 
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
