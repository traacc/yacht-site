<?php

namespace App\Filament\User\Resources\Teams;

use App\Filament\User\Resources\Teams\Pages\EditTeam;
use App\Filament\User\Resources\Teams\Pages\ManageTeams;
use App\Models\Team;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = 'team';

    public static function getRelations(): array
    {
        return [
            
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Моя команда'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Мои команды'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('picture')
                    ->label('Добавить фотографию')
                    ->image()
                    ->avatar()
                    ->directory('owners')
                    ->disk('public')->columnSpanFull(),
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите названия команды')
                    ->required()->columnSpanFull(),
                Textarea::make('description')
                    ->label('Описание')
                    ->placeholder('Описание команды')
                    ->columnSpanFull(),

                Repeater::make('albums')
                    ->label('Галерея (альбомы)')
                    ->relationship('albums')
                    ->addActionLabel('Добавить альбом')
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->collapsible()
                    ->schema([
                        TextInput::make('title')
                            ->label('Название альбома')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(2)
                            ->columnSpanFull(),
                        FileUpload::make('cover_url')
                            ->label('Обложка')
                            ->image()
                            ->directory('albums/covers')
                            ->disk('public')
                            ->columnSpanFull(),
                        Repeater::make('media')
                            ->label('Фотографии')
                            ->relationship('media')
                            ->addActionLabel('Добавить фото')
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->schema([
                                Hidden::make('type')->default('photo'),
                                FileUpload::make('url')
                                    ->label('Фото')
                                    ->image()
                                    ->directory('albums/photos')
                                    ->disk('public')
                                    ->required(),
                                Hidden::make('sort_order')->default(0),
                            ]),
                    ]),

                Hidden::make('organizer_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('activeMembers'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()->label('Команда'),
                TextColumn::make('organizer.name')
                    ->searchable()->label('Капитан'),
                TextColumn::make('active_members_count')
                    ->label('Участники')
                    ->sortable(),
                TextColumn::make('approval_status')
                    ->label('Статус')
                    ->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'На рассмотрении',
                    'approved' => 'Одобрена',
                    'rejected' => 'Отклонена',
                    'withdrawn' => 'Отозвана',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'withdrawn' => 'gray',
                    default => 'gray',
                }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])->emptyStateHeading('Записей пока нет')
            ->recordActions([
                //EditAction::make()->hiddenLabel(),
                //DeleteAction::make()->hiddenLabel(),
                //ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //DeleteBulkAction::make(),
                    //ForceDeleteBulkAction::make(),
                    //RestoreBulkAction::make(),
                ]),
            ])->stackedOnMobile();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTeams::route('/'),
            //'edit' => EditTeam::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleForUser(auth()->user());
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organizer_id'] = auth()->id();

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        // Запрещаем изменение organizer_id — всегда остаётся текущий пользователь
        $data['organizer_id'] = auth()->id();

        return $data;
    }
}
