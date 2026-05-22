<?php

namespace App\Filament\Resources\RegattaResults;

use App\Filament\Resources\RegattaResults\Pages\ManageRegattaResults;
use App\Models\RegattaResult;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegattaResultResource extends Resource
{
    protected static ?string $model = RegattaResult::class;

    protected static string|BackedEnum|null $navigationIcon = 'cup';


    public static function getModelLabel(): string
    {
        return 'Результат регаты';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Результаты регат';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('regatta_id')
                    ->label('Регата')
                    ->relationship('regatta', 'name')
                    ->required()
                    ->columnSpanFull(),

                Select::make('result_type')
                    ->label('Тип результата')
                    ->options([
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                    ])
                    ->required()
                    ->default('preliminary'),

                Select::make('source')
                    ->label('Источник')
                    ->options([
                        'manual'   => 'Вручную',
                        'imported' => 'Импортирован',
                    ])
                    ->required()
                    ->default('manual'),

                Repeater::make('items')
                    ->label('Результаты участников')
                    ->relationship('items')
                    ->schema([
                        Select::make('team_id')
                            ->label('Команда')
                            ->relationship('team', 'name')
                            ->required()
                            ->columnSpan(2),

                        Select::make('yacht_id')
                            ->label('Яхта')
                            ->relationship('yacht', 'name')
                            ->nullable()
                            ->columnSpan(2),

                        TextInput::make('total_points')
                            ->label('Очки')
                            ->numeric()
                            ->required()
                            ->default(0.0),

                        TextInput::make('final_position')
                            ->label('Место')
                            ->numeric()
                            ->nullable(),
                    ])
                    ->columns(6)
                    ->addActionLabel('Добавить участника')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('regatta.name')
                    ->label('Регата'),
                TextEntry::make('result_type')
                    ->label('Тип')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                        default       => $state,
                    }),
                TextEntry::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'manual'   => 'Вручную',
                        'imported' => 'Импортирован',
                        default    => $state,
                    }),
                TextEntry::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime()
                    ->placeholder('-'),

                RepeatableEntry::make('items')
                    ->label('Результаты участников')
                    ->schema([
                        TextEntry::make('final_position')
                            ->label('Место')
                            ->placeholder('-'),
                        TextEntry::make('team.name')
                            ->label('Команда'),
                        TextEntry::make('yacht.name')
                            ->label('Яхта')
                            ->placeholder('-'),
                        TextEntry::make('total_points')
                            ->label('Очки')
                            ->numeric(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('result_type')
                    ->label('Тип')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                        default       => $state,
                    }),
                TextColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'manual'   => 'Вручную',
                        'imported' => 'Импортирован',
                        default    => $state,
                    }),
                TextColumn::make('items_count')
                    ->label('Участников')
                    ->counts('items')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
            'index' => ManageRegattaResults::route('/'),
        ];
    }
}
