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

    protected static string|BackedEnum|null $navigationIcon = 'regatta';

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
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите название регаты')
                    ->required(),
                Select::make('season_id')
                    ->label('Сезон')
                    ->relationship('season', 'year')
                    ->required(),
                TextInput::make('level_coefficient')
                    ->label('Коэффициент соревнований')
                    ->placeholder('Введите коэффициент соревнований')
                    ->required()
                    ->numeric()
                    ->default(1.0)->columnSpanFull(),
                DatePicker::make('date_start')
                    ->label('Дата начала')
                    ->required(),
                DatePicker::make('date_end')
                    ->label('Дата окончания')
                    ->required(),

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

                TextInput::make('location')
                    ->label('Локацию')
                    ->placeholder('Выберите локацию'),
                TextInput::make('water_area')
                    ->label('Акватория')
                    ->placeholder('Введите акваторию'),
                Textarea::make('description')
                    ->label('О регате')
                    ->placeholder('Описание о регате')
                    ->columnSpanFull(),
                FileUpload::make('background_image')
                    ->label('Загрузить обложку'),

                Textarea::make('map_html')
                    ->label('Карта (HTML)')
                    ->placeholder('HTML-код карты')
                    ->columnSpanFull(),
                Textarea::make('prizes')
                    ->label('Призы')
                    ->placeholder('Описание призового фонда')
                    ->columnSpanFull(),
                Textarea::make('regulations')
                    ->label('Регламент')
                    ->placeholder('Описание регламента')
                    ->columnSpanFull(),
                Toggle::make('is_archived')
                    ->label('Архивная'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Регата')
                    ->searchable(),
                TextColumn::make('season.year')
                    ->label('Сезон')
                    ->searchable(),
                TextColumn::make('date')
                    ->label('Дата')
                    ->getStateUsing(function (Regatta $regatta): string {
                        // Доступ к текущей строке (модели) через $record
                        return $regatta->dateRange();
                    }),
                TextColumn::make('water_area')
                    ->label('Акватория')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Статус')->badge()
                ->getStateUsing(function (Regatta $regatta): string {
                    if($regatta->startsInLessThanMonth())
                        return 'closest';
                    else if ($regatta->isUpcoming()) {
                        return 'planned';
                    } else if ($regatta->isFinished()) {
                        return 'completed';
                    }
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'closest' => 'Ближайшая',
                    'planned' => 'Планируемая',
                    'completed' => 'Завершена',
                    default => $state,
                })->color(fn (string $state): string => match ($state) {
                    'planned' => 'warning',
                    'completed' => 'success',
                    'closest' => 'danger',
                    default => 'gray',
                }),
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
