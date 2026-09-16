<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\CrewJoinRequests;

use App\Actions\RegattaEntry\ResolveCrewJoinRequestAction;
use App\Enums\CrewJoinRequestStatus;
use App\Filament\User\Resources\CrewJoinRequests\Pages\ManageCrewJoinRequests;
use App\Models\CrewJoinRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Отклики «Хочу в этот экипаж» на заявки пользователя в ЛК.
 *
 * Видит и решает автор заявки экипажа либо капитан/администратор её команды
 * (@see CrewJoinRequest::scopeResolvableBy()). Записи для действий Filament
 * берёт из getEloquentQuery(), поэтому чужой отклик не принять и по id.
 */
class CrewJoinRequestResource extends Resource
{
    protected static ?string $model = CrewJoinRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'Отклик в экипаж';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Отклики в экипаж';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Пункт меню — только тем, кому уже откликались. */
    public static function shouldRegisterNavigation(): bool
    {
        return static::getEloquentQuery()->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->resolvableBy($user)
            ->with(['regattaEntry.regatta', 'regattaEntry.team'])
            ->latest();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->pending()->count();

        return $count > 0 ? (string) $count : null;
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
                    ->sortable(),
                TextColumn::make('regattaEntry.team.name')
                    ->label('Экипаж')
                    ->placeholder('—'),
                TextColumn::make('message')
                    ->label('Сообщение')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (CrewJoinRequest $record): ?string => $record->message),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Получен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Откликов пока нет')
            ->emptyStateDescription('Здесь появятся желающие попасть в ваш экипаж, если при подаче заявки открыт добор участников.')
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
                    ->modalHeading('Принять в экипаж?')
                    ->modalDescription(fn (CrewJoinRequest $record): string => $record->name.' будет добавлен(а) в экипаж заявки.')
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
                    ->modalHeading('Отклонить отклик?')
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
     * пока модалка была открыта.
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
