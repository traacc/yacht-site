<?php

declare(strict_types=1);

namespace App\Filament\Resources\CrewJoinRequests;

use App\Actions\RegattaEntry\ResolveCrewJoinRequestAction;
use App\Enums\CrewJoinRequestStatus;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Concerns\ScopesToOwnedRegattas;
use App\Filament\Resources\CrewJoinRequests\Pages\ManageCrewJoinRequests;
use App\Models\CrewJoinRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Отклики «Хочу в этот экипаж» на клубных регатах.
 *
 * Решение принимает экипаж, но администрация видит все отклики и может
 * ответить за экипаж — заявки на сайте в любом случае идут через админа.
 */
class CrewJoinRequestResource extends Resource
{
    use RestrictsAccessByRole;
    use ScopesToOwnedRegattas;

    protected static ?string $model = CrewJoinRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Заявки в экипажи';

    protected static ?int $navigationSort = 5;

    protected static string|UnitEnum|null $navigationGroup = 'Регаты';

    /** Отклик связан с регатой через заявку экипажа. */
    protected static function regattaRelationPath(): ?string
    {
        return 'regattaEntry.regatta';
    }

    public static function getModelLabel(): string
    {
        return 'Заявка в экипаж';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки в экипажи';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeToOwnedRegattas(parent::getEloquentQuery())
            ->with(['regattaEntry.regatta', 'regattaEntry.team'])
            ->latest();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Кандидат')
                    ->searchable()
                    ->description(fn (CrewJoinRequest $record): string => $record->email
                        .($record->phone ? ' · '.$record->phone : '')),
                TextColumn::make('regattaEntry.regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('regattaEntry.team.name')
                    ->label('Экипаж')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('message')
                    ->label('Сообщение')
                    ->limit(60)
                    ->tooltip(fn (CrewJoinRequest $record): ?string => $record->message)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Подана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Откликов пока нет')
            ->emptyStateDescription('Здесь появятся желающие попасть в экипажи клубных регат.')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(CrewJoinRequestStatus::options()),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label('Принять')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CrewJoinRequest $record): bool => $record->isPending())
                    ->schema([
                        Textarea::make('note')
                            ->label('Комментарий кандидату')
                            ->rows(3),
                    ])
                    ->action(fn (CrewJoinRequest $record, array $data) => static::resolve(
                        fn () => app(ResolveCrewJoinRequestAction::class)
                            ->accept($record, auth()->user(), $data['note'] ?? null),
                        'Кандидат добавлен в экипаж',
                    )),
                Action::make('decline')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (CrewJoinRequest $record): bool => $record->isPending())
                    ->schema([
                        Textarea::make('note')
                            ->label('Причина отказа')
                            ->rows(3),
                    ])
                    ->action(fn (CrewJoinRequest $record, array $data) => static::resolve(
                        fn () => app(ResolveCrewJoinRequestAction::class)
                            ->decline($record, auth()->user(), $data['note'] ?? null),
                        'Отклик отклонён',
                    )),
            ]);
    }

    /**
     * Ошибки Action показываем уведомлением: экипаж мог укомплектоваться,
     * пока админ держал модалку открытой.
     */
    private static function resolve(callable $callback, string $successTitle): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Не удалось выполнить действие')
                ->body(implode(' ', $e->validator->errors()->all()))
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title($successTitle)->success()->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCrewJoinRequests::route('/'),
        ];
    }
}
