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

    public static function getRelations(): array
    {
        return [
            RelationManagers\AlbumsRelationManager::class,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Название команды')
                    ->required(),
                Textarea::make('description')
                    ->label('Описание')
                    ->placeholder('Описание команды')
                    ->columnSpanFull(),
                Select::make('organizer_id')
                    ->label('Организатор')
                    ->relationship('organizer', 'name'),
                Toggle::make('is_archived')
                    ->label('Архивная'),
                Select::make('approval_status')
                    ->label('Статус одобрения')
                    ->placeholder('Выберите статус')
                    ->options(['pending' => 'На рассмотрении', 'approved' => 'Одобрена', 'rejected' => 'Отклонена'])
                    ->default('pending')
                    ->required(),
                TextInput::make('rejection_reason')
                    ->label('Причина отклонения')
                    ->placeholder('Причина отклонения'),
                TextInput::make('rejection_comment')
                    ->label('Комментарий к отклонению')
                    ->placeholder('Комментарий к отклонению'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('activeMembers'))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('active_members_count')
                    ->label('Участники')
                    ->sortable(),
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
                TextColumn::make('rejection_reason')
                    ->label('Причина отклонения')
                    ->searchable(),
                TextColumn::make('rejection_comment')
                    ->label('Комментарий')
                    ->searchable(),
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
            'index' => ManageTeams::route('/'),
            'edit' => EditTeam::route('/{record}/edit'),
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
