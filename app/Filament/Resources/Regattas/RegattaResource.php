<?php

namespace App\Filament\Resources\Regattas;

use App\Filament\Resources\Regattas\Pages\ManageRegattas;
use App\Models\Regatta;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegattaResource extends Resource
{
    protected static ?string $model = Regatta::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getModelLabel(): string
    {
        return 'Регата'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Регаты'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->label('Сезон')
                    ->relationship('season', 'id')
                    ->required(),
                Select::make('series_id')
                    ->label('Серия')
                    ->relationship('series', 'name'),
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Название регаты')
                    ->required(),
                TextInput::make('level_coefficient')
                    ->label('Коэффициент уровня')
                    ->placeholder('1.0')
                    ->required()
                    ->numeric()
                    ->default(1.0),
                DatePicker::make('date_start')
                    ->label('Дата начала')
                    ->required(),
                DatePicker::make('date_end')
                    ->label('Дата окончания')
                    ->required(),
                FileUpload::make('background_image')
                    ->label('Фоновое изображение'),
                TextInput::make('location')
                    ->label('Местоположение')
                    ->placeholder('Город, страна'),
                TextInput::make('water_area')
                    ->label('Акватория')
                    ->placeholder('Название акватории'),
                Textarea::make('description')
                    ->label('Описание')
                    ->placeholder('Описание регаты')
                    ->columnSpanFull(),
                Textarea::make('regulations')
                    ->label('Положение')
                    ->placeholder('Текст положения')
                    ->columnSpanFull(),
                Textarea::make('map_html')
                    ->label('Карта (HTML)')
                    ->placeholder('HTML-код карты')
                    ->columnSpanFull(),
                TextInput::make('race_days_count')
                    ->label('Количество гоночных дней')
                    ->placeholder('1')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('races_count')
                    ->label('Количество гонок')
                    ->placeholder('1')
                    ->required()
                    ->numeric()
                    ->default(1),
                Textarea::make('prizes')
                    ->label('Призы')
                    ->placeholder('Описание призового фонда')
                    ->columnSpanFull(),
                Toggle::make('is_archived')
                    ->label('Архивная'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('season.id')
                    ->label('Сезон')
                    ->searchable(),
                TextColumn::make('series.name')
                    ->label('Серия')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('level_coefficient')
                    ->label('Коэффициент уровня')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('date_start')
                    ->label('Дата начала')
                    ->date()
                    ->sortable(),
                TextColumn::make('date_end')
                    ->label('Дата окончания')
                    ->date()
                    ->sortable(),
                ImageColumn::make('background_image')
                    ->label('Фоновое изображение'),
                TextColumn::make('location')
                    ->label('Местоположение')
                    ->searchable(),
                TextColumn::make('water_area')
                    ->label('Акватория')
                    ->searchable(),
                TextColumn::make('race_days_count')
                    ->label('Гоночных дней')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('races_count')
                    ->label('Количество гонок')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_archived')
                    ->label('Архивная')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Удалено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattas::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
