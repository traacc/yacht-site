<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserReplacements;

use App\Enums\SystemRole;
use App\Filament\Resources\UserReplacements\Pages\ManageUserReplacements;
use App\Models\UserReplacement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Журнал замен пользователей — только для чтения.
 * Записи создаёт App\Actions\User\ReplaceUserAction.
 */
class UserReplacementResource extends Resource
{
    protected static ?string $model = UserReplacement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Команды и участники';

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return 'Замена пользователя';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Журнал замен';
    }

    /**
     * Только администратор — как и сама замена.
     * Ресурс исключён из матрицы прав (@see \App\Support\AccessControl::excluded()).
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->system_role === SystemRole::Admin;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата и время')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('from_name')
                    ->label('Кого')
                    ->searchable()
                    ->description(fn (UserReplacement $record): string => $record->from_deleted ? 'учётка удалена' : 'учётка оставлена'),
                TextColumn::make('to_name')
                    ->label('На кого')
                    ->searchable(),
                TextColumn::make('changes_count')
                    ->label('Изменено')
                    ->getStateUsing(fn (UserReplacement $record): string => 'экипажей: '.count($record->summary['entries'] ?? [])
                        .', команд: '.count($record->summary['teams'] ?? [])),
                TextColumn::make('performed_by_name')
                    ->label('Кто')
                    ->placeholder('—'),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Замен пока не было')
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('details')
                    ->label('Подробнее')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading('Замена пользователя')
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalContent(fn (UserReplacement $record) => view('filament.resources.user-replace-preview', [
                        'plan' => $record->summary,
                        'done' => true,
                        'fromLabel' => $record->from_name,
                        'toLabel' => $record->to_name,
                        'fromName' => $record->from_name,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть'),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserReplacements::route('/'),
        ];
    }
}
