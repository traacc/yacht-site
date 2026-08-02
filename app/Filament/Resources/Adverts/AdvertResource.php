<?php

declare(strict_types=1);

namespace App\Filament\Resources\Adverts;

use App\Actions\Advert\ModerateAdvertAction;
use App\Enums\AdvertKind;
use App\Enums\AdvertStatus;
use App\Enums\AdvertType;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\Adverts\Pages\ManageAdverts;
use App\Models\Advert;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Модерация объявлений всех досок.
 *
 * Отдельной «очереди» (второго ресурса поверх той же модели, как у заявок на
 * регату) не заводим: фильтра по статусу и бейджа со счётчиком достаточно,
 * а дублировать таблицу и действия было бы дороже.
 */
class AdvertResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = Advert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Объявления';

    protected static ?int $navigationSort = 30;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public static function getModelLabel(): string
    {
        return 'Объявление';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Объявления';
    }

    /** Объявления заводят только пользователи из личного кабинета. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('type')
                ->label('Раздел')
                ->badge()
                ->formatStateUsing(fn (AdvertType $state): string => $state->label()),
            TextEntry::make('status')
                ->label('Статус')
                ->badge()
                ->formatStateUsing(fn (AdvertStatus $state): string => $state->label())
                ->color(fn (AdvertStatus $state): string => $state->color()),
            TextEntry::make('author.name')
                ->label('Автор'),
            TextEntry::make('author.email')
                ->label('Email автора')
                ->placeholder('—'),
            TextEntry::make('title')
                ->label('Заголовок')
                ->columnSpanFull(),
            TextEntry::make('description')
                ->label('Описание')
                ->columnSpanFull(),
            TextEntry::make('details')
                ->label(fn (Advert $record): string => $record->type->detailsLabel() ?? 'Подробности')
                ->visible(fn (Advert $record): bool => filled($record->details))
                ->columnSpanFull(),
            TextEntry::make('kind')
                ->label('Вид')
                ->badge()
                ->state(fn (Advert $record): ?string => $record->kindLabel())
                ->visible(fn (Advert $record): bool => $record->kind !== null),
            TextEntry::make('category.title')
                ->label('Категория')
                ->placeholder('—'),
            TextEntry::make('position')
                ->label('Позиция')
                ->state(fn (Advert $record): ?string => $record->position?->label())
                ->visible(fn (Advert $record): bool => $record->position !== null),
            TextEntry::make('sport_category')
                ->label('Разряд')
                ->state(fn (Advert $record): ?string => $record->sport_category?->getLabel())
                ->visible(fn (Advert $record): bool => $record->sport_category !== null),
            TextEntry::make('yacht_name')
                ->label('Яхта')
                ->state(fn (Advert $record): ?string => $record->yachtLabel())
                ->placeholder('—'),
            TextEntry::make('regattas.name')
                ->label('Регаты')
                ->badge()
                ->visible(fn (Advert $record): bool => $record->regattas->isNotEmpty())
                ->columnSpanFull(),
            TextEntry::make('date_from')
                ->label('Когда')
                ->state(fn (Advert $record): ?string => $record->datesLabel())
                ->visible(fn (Advert $record): bool => $record->datesLabel() !== null),
            TextEntry::make('price')
                ->label('Цена')
                ->state(fn (Advert $record): string => $record->priceLabel()),
            TextEntry::make('deposit')
                ->label('Залог')
                ->state(fn (Advert $record): ?string => $record->depositLabel())
                ->visible(fn (Advert $record): bool => $record->deposit !== null),
            TextEntry::make('city')
                ->label('Город')
                ->placeholder('—'),
            TextEntry::make('contact_phone')
                ->label('Телефон для публикации')
                ->placeholder('—'),
            TextEntry::make('contact_telegram')
                ->label('Telegram')
                ->placeholder('—'),
            TextEntry::make('contact_email')
                ->label('Email для публикации')
                ->placeholder('—'),
            TextEntry::make('created_at')
                ->label('Подано')
                ->dateTime('d.m.Y H:i'),
            TextEntry::make('rejection_reason')
                ->label('Причина отказа')
                ->placeholder('—')
                ->columnSpanFull(),

            SpatieMediaLibraryImageEntry::make('photos')
                ->label('Фотографии')
                ->collection(Advert::PHOTOS)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('Фото')
                    ->collection(Advert::PHOTOS)
                    ->limit(1),
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),
                TextColumn::make('type')
                    ->label('Раздел')
                    ->badge()
                    ->formatStateUsing(fn (AdvertType $state): string => $state->label()),
                TextColumn::make('author.name')
                    ->label('Автор')
                    ->searchable(),
                TextColumn::make('category.title')
                    ->label('Категория')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->state(fn (Advert $record): string => $record->priceLabel()),
                TextColumn::make('city')
                    ->label('Город')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (AdvertStatus $state): string => $state->label())
                    ->color(fn (AdvertStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Подано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('moderatedBy.name')
                    ->label('Модератор')
                    ->placeholder('—')
                    ->description(fn (Advert $record): ?string => $record->moderated_at?->format('d.m.Y H:i'))
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Раздел')
                    ->options(AdvertType::options()),

                SelectFilter::make('kind')
                    ->label('Вид')
                    ->options(AdvertKind::options()),
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(AdvertStatus::options()),
                Filter::make('pending')
                    ->label('Только на модерации')
                    ->query(fn (Builder $query): Builder => $query->pending()),
            ])
            ->emptyStateHeading('Объявлений пока нет')
            ->emptyStateDescription('Здесь появятся объявления, поданные пользователями из личного кабинета.')
            ->recordActions([
                ViewAction::make()
                    ->label('Просмотр')
                    ->modalHeading(fn (Advert $record): string => $record->title)
                    ->extraModalFooterActions([
                        Action::make('approveFromView')
                            ->label('Опубликовать')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->visible(fn (Advert $record): bool => ! $record->isPublished())
                            ->action(function (Advert $record): void {
                                app(ModerateAdvertAction::class)->approve($record, auth()->user());
                                static::approvedNotification()->send();
                            })
                            ->cancelParentActions(),
                        Action::make('rejectFromView')
                            ->label('Отклонить')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->schema(static::rejectForm())
                            ->action(function (Advert $record, array $data): void {
                                app(ModerateAdvertAction::class)->reject($record, $data['rejection_reason'] ?? null, auth()->user());
                                static::rejectedNotification()->send();
                            })
                            ->cancelParentActions(),
                    ]),

                Action::make('approve')
                    ->label('Опубликовать')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Advert $record): bool => ! $record->isPublished())
                    ->requiresConfirmation()
                    ->modalHeading('Опубликовать объявление?')
                    ->modalDescription(fn (Advert $record): string => '«'.$record->title.'» появится на витрине, автор получит уведомление.')
                    ->modalSubmitActionLabel('Опубликовать')
                    ->action(function (Advert $record): void {
                        app(ModerateAdvertAction::class)->approve($record, auth()->user());
                        static::approvedNotification()->send();
                    }),

                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Advert $record): bool => $record->status !== AdvertStatus::Rejected)
                    ->modalHeading('Отклонить объявление?')
                    ->modalSubmitActionLabel('Отклонить')
                    ->schema(static::rejectForm())
                    ->action(function (Advert $record, array $data): void {
                        app(ModerateAdvertAction::class)->reject($record, $data['rejection_reason'] ?? null, auth()->user());
                        static::rejectedNotification()->send();
                    }),

                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Удалить объявление?')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Объявление удалено'),
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveAll')
                        ->label('Опубликовать выбранные')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $moderator = auth()->user();
                            $action = app(ModerateAdvertAction::class);

                            $records->each(fn (Advert $advert) => $action->approve($advert, $moderator));

                            static::approvedNotification()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('rejectAll')
                        ->label('Отклонить выбранные')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->schema(static::rejectForm())
                        ->action(function (Collection $records, array $data): void {
                            $moderator = auth()->user();
                            $action = app(ModerateAdvertAction::class);
                            $reason = $data['rejection_reason'] ?? null;

                            $records->each(fn (Advert $advert) => $action->reject($advert, $reason, $moderator));

                            static::rejectedNotification()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /** @return list<Component> */
    protected static function rejectForm(): array
    {
        return [
            Textarea::make('rejection_reason')
                ->label('Причина отказа')
                ->placeholder('Будет видна автору в личном кабинете (необязательно)')
                ->rows(3)
                ->maxLength(1000),
        ];
    }

    protected static function approvedNotification(): Notification
    {
        return Notification::make()
            ->success()
            ->title('Объявление опубликовано');
    }

    protected static function rejectedNotification(): Notification
    {
        return Notification::make()
            ->danger()
            ->title('Объявление отклонено');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['author', 'category', 'yacht', 'moderatedBy', 'regattas']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', AdvertStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdverts::route('/'),
        ];
    }
}
