<?php

namespace App\Filament\Resources\TeamRatings;

use App\Filament\Resources\TeamRatings\Pages\ManageTeamRatings;
use App\Models\TeamRating;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Builder;

class TeamRatingResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = TeamRating::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 9;

    public static function getModelLabel(): string
    {
        return 'Командный рейтинг';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Командные рейтинги';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->relationship('season', 'year',
                    modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('year'),)
                    ->label('Сезон')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('year')
                            ->label('Год')
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2099),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Дата начала сезона')
                            ->displayFormat('d M Y')
                            ->native(false)
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Дата окончания сезона')
                            ->displayFormat('d M Y')
                            ->native(false)
                            ->required(),
                    ])
                    ->createOptionUsing(fn (array $data): string => \App\Models\Season::create($data)->id)
                    ->required(),
                Select::make('team_id')
                    ->label('Команда')
                    ->relationship('team', 'name')
                    ->searchable()
                    ->required()
                    ->unique(
                        table: 'team_ratings',
                        column: 'team_id',
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
            ->defaultSort('rank_position')
            ->columns([
                TextColumn::make('season.year')
                    ->label('Сезон')
                    ->searchable(),
                TextColumn::make('team.name')
                    ->label('Команда')
                    ->searchable(),
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
                EditAction::make()->modalHeading('Редактировать командный рейтинг'),
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
            'index' => ManageTeamRatings::route('/'),
        ];
    }
}
