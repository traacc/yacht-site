<?php

namespace App\Filament\Resources\TeamMembers;

use App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers;
use App\Models\TeamMember;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TeamMemberResource extends Resource
{
    protected static ?string $model = TeamMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


    public static function getModelLabel(): string
    {
        return 'Член команды'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Члены команды'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('team_id')
                    ->label('Команда')
                    ->relationship('team', 'name')
                    ->required(),
                Select::make('user_id')
                    ->label('Пользователь')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('role')
                    ->label('Роль')
                    ->placeholder('Выберите роль')
                    ->options(['organizer' => 'Организатор', 'admin' => 'Администратор', 'member' => 'Участник'])
                    ->default('member')
                    ->required(),
                Select::make('status')
                    ->label('Статус')
                    ->placeholder('Выберите статус')
                    ->options(['invited' => 'Приглашён', 'active' => 'Активен', 'declined' => 'Отклонён'])
                    ->default('invited')
                    ->required(),
                DateTimePicker::make('joined_at')
                    ->label('Дата присоединения'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('team.name')
                    ->label('Команда')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Роль')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('joined_at')
                    ->label('Дата присоединения')
                    ->dateTime()
                    ->sortable(),
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
            ])
            ->filters([
                //
            ])
            ->recordActions([
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
            'index' => ManageTeamMembers::route('/'),
        ];
    }
}
