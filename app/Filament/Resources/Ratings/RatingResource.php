<?php

namespace App\Filament\Resources\Ratings;

use App\Filament\Resources\Ratings\Pages\ManageRatings;
use App\Models\Rating;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;


class RatingResource extends Resource
{
    protected static ?string $model = Rating::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getModelLabel(): string
    {
        return 'Рейтинг'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Рейтинги'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->relationship('season', 'year')
                    ->label('Сезон')
                    ->required(),
                Select::make('rating_type')
                    ->label('Тип рейтинга')
                    ->options(['team' => 'Командный', 'personal' => 'Личный'])
                    ->required()
                    ->live(),
                Select::make('team_id')
                    ->label('Команда')
                    ->relationship('team', 'name')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('rating_type') === 'team')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('rating_type') === 'team')
                    ->unique(
                        table: 'ratings',
                        column: 'team_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule, \Filament\Schemas\Components\Utilities\Get $get) =>
                            $rule->where('season_id', $get('season_id')),
                    ),
                Select::make('user_id')
                    ->label('Участник')
                    ->relationship('user', 'name')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('rating_type') === 'personal')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('rating_type') === 'personal')
                    ->unique(
                        table: 'ratings',
                        column: 'user_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule, \Filament\Schemas\Components\Utilities\Get $get) =>
                            $rule->where('season_id', $get('season_id')),
                    ),
                TextInput::make('total_points')
                    ->label('Всего очков')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('rank_position')
                    ->label('Место')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('season.year')
                    ->label('Сезон')
                    ->searchable(),
                TextColumn::make('team.name')
                    ->label('Команда')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Участник')
                    ->searchable(),
                TextColumn::make('rating_type')
                    ->label('Тип рейтинга')->formatStateUsing(fn (string $state): string => match ($state) {'team' => 'Командный', 'personal' => 'Личный'})
                    ->badge(),
                TextColumn::make('total_points')
                    ->label('Всего очков')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rank_position')
                    ->label('Место')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать рейтинг'),
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
            'index' => ManageRatings::route('/'),
        ];
    }
}
