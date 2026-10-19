<?php

declare(strict_types=1);

namespace App\Filament\Resources\Series;

use App\Filament\Concerns\FiltersBySeason;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\Series\Pages\ManageSeries;
use App\Models\Series;
use App\Services\SeasonContext;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SeriesResource extends Resource
{
    use FiltersBySeason;
    use RestrictsAccessByRole;

    protected static ?string $model = Series::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    protected static string|UnitEnum|null $navigationGroup = 'Регаты';

    public static function getModelLabel(): string
    {
        return 'Серия';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Серии';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите название серии')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('season_id')
                    ->label('Сезон')
                    ->relationship('season', 'year',
                        modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('year'))
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?string => app(SeasonContext::class)->current()?->id)
                    ->live()
                    ->required(),
                Textarea::make('description')
                    ->label('Описание')
                    ->placeholder('Описание серии')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('regattas')
                    ->label('Регаты')
                    // Только регаты выбранного сезона, иначе список копится годами.
                    ->relationship('regattas', 'name',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                            ->when($get('season_id'), fn (Builder $q, string $seasonId) => $q->where('season_id', $seasonId))
                            ->orderByDesc('date_start'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Выберите регаты, которые входят в эту серию')
                    ->columnSpanFull(),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('regattas'))
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('season.year')
                    ->label('Сезон')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('regattas_count')
                    ->label('Регат')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('description')
                    ->label('Описание')
                    ->limit(60)
                    ->toggleable(),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Серий пока нет')
            ->defaultSort('created_at', 'desc')
            ->filters([
                static::seasonFilter(),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать серию'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeries::route('/'),
        ];
    }
}
