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
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Regatta::query())
            ->headerActions([
            Action::make('view_all')
                    ->label('Все соревнования') // Текст ссылки
                    ->icon('heroicon-m-arrow-top-right-on-square') // Иконка рядом (по желанию)
                    ->color('gray') // Цвет (primary, gray, success и т.д.)
                    ->link() // ТРАНСФОРМИРУЕТ кнопку в аккуратную текстовую ссылку
                    ->url(fn () => \App\Filament\Resources\RegattaResource::getUrl('index')), 
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable()->label('Название'),
                TextColumn::make('season.year')
                    ->searchable()->label('Сезон'),
                TextColumn::make('dateRange')
                    ->label('Дата')
                    ->getStateUsing(fn (Regatta $record): string => $record->dateRange())
                    ->sortable(query: fn (Builder $q, string $dir) => $q->orderBy('date_start', $dir)),
                TextColumn::make('water_area')
                    ->searchable()->label('Акватория')->columnSpanFull(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
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
