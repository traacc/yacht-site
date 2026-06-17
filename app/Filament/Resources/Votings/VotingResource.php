<?php

declare(strict_types=1);

namespace App\Filament\Resources\Votings;

use App\Enums\VotingStatus;
use App\Filament\Resources\Votings\Pages\ManageVotings;
use App\Models\Voting;
use BackedEnum;
use UnitEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VotingResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = Voting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 15;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public static function getModelLabel(): string
    {
        return 'Голосование';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Голосования';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->placeholder('Введите заголовок голосования')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Описание')
                            ->placeholder('Описание голосования')
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Статус')
                            ->options(VotingStatus::options())
                            ->default(VotingStatus::Active->value)
                            ->required(),
                        /*
                        Toggle::make('is_anonymous')
                            ->label('Анонимное')
                            ->helperText('Скрывать, кто как проголосовал')
                            ->default(false),
                        Toggle::make('allow_multiple')
                            ->label('Несколько вариантов')
                            ->helperText('Можно выбрать несколько вариантов')
                            ->default(false),
                        */
                    ]),

                Section::make('Период проведения')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Начало')
                            ->displayFormat('d.m.Y H:i')
                            ->seconds(false)
                            ->native(false),
                        DateTimePicker::make('ends_at')
                            ->label('Окончание')
                            ->displayFormat('d.m.Y H:i')
                            ->seconds(false)
                            ->native(false)
                            ->after('starts_at'),
                    ]),

                Section::make('Варианты ответа')
                    ->schema([
                        Repeater::make('options')
                            ->label('Варианты')
                            ->relationship('options')
                            ->hiddenLabel()
                            ->addActionLabel('Добавить вариант')
                            ->defaultItems(0)
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Вариант')
                                    ->placeholder('Текст варианта')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['options', 'votes']))
            ->columns([
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->sortable(),
                /*
                IconColumn::make('is_anonymous')
                    ->label('Анонимное')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('allow_multiple')
                    ->label('Мультивыбор')
                    ->boolean()
                    ->toggleable(),
                */
                TextColumn::make('options_count')
                    ->label('Вариантов')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('votes_count')
                    ->label('Голосов')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('starts_at')
                    ->label('Начало')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ends_at')
                    ->label('Окончание')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('author.name')
                    ->label('Автор')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Голосований пока нет')
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(VotingStatus::options()),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать голосование'),
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
            'index' => ManageVotings::route('/'),
        ];
    }
}
