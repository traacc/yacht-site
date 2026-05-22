<?php

namespace App\Filament\Resources\Teams;

use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ManageTeams;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = 'team';

    public static function getModelLabel(): string
    {
        return 'Команда'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Команды'; // Название во множественном числе
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
                Select::make('organizer_id')
                    ->label('Капитан')
                    ->relationship('organizer', 'name')
                    ->placeholder('Выберите капитана')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Описание')
                    ->placeholder('Описание команды')
                    ->columnSpanFull()->columnSpanFull(),
                Select::make('approval_status')
                    ->label('Статус одобрения')
                    ->placeholder('Выберите статус')
                    ->options(['pending' => 'На рассмотрении', 'approved' => 'Одобрена', 'rejected' => 'Отклонена'])
                    ->default('pending')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('activeMembers'))
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('organizer.name')
                    ->label('Организатор')
                    ->searchable(),
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
            'index' => ManageTeams::route('/'),
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
