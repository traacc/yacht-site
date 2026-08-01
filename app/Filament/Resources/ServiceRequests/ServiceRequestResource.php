<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\ServiceRequests\Pages\ManageServiceRequests;
use App\Models\ServiceRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Заявки раздела «Услуги» (ТЗ 3-го этапа, п. 7).
 *
 * Одна таблица на все подразделы — фильтра по типу достаточно; специфичные
 * поля подраздела живут в payload и показываются в карточке заявки.
 * Создавать заявки из админки нельзя — они приходят только с сайта.
 */
class ServiceRequestResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Заявки на услуги';

    protected static ?int $navigationSort = 11;

    public static function getModelLabel(): string
    {
        return 'Заявка на услугу';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на услуги';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('type')
                ->label('Услуга')
                ->badge()
                ->formatStateUsing(fn (ServiceType $state): string => $state->label()),
            TextEntry::make('status')
                ->label('Статус')
                ->badge()
                ->formatStateUsing(fn (ServiceRequestStatus $state): string => $state->label())
                ->color(fn (ServiceRequestStatus $state): string => $state->color()),
            TextEntry::make('subject_id')
                ->label('Объект заявки')
                ->state(fn (ServiceRequest $record): ?string => $record->subjectLabel())
                ->url(fn (ServiceRequest $record): ?string => $record->subjectUrl())
                ->openUrlInNewTab()
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('name')
                ->label('Заявитель'),
            TextEntry::make('phone')
                ->label('Телефон')
                ->copyable(),
            TextEntry::make('email')
                ->label('Email')
                ->placeholder('—')
                ->copyable(),
            TextEntry::make('user.name')
                ->label('Пользователь сайта')
                ->placeholder('Не авторизован'),
            TextEntry::make('date_start')
                ->label('Даты')
                ->state(fn (ServiceRequest $record): ?string => $record->dateRangeLabel())
                ->placeholder('—'),
            TextEntry::make('quantity')
                ->label(fn (ServiceRequest $record): string => $record->type->quantityLabel())
                ->placeholder('—'),

            // Специфика подраздела: json превращает в подписи модель, чтобы
            // письмо и админка показывали заявку одинаково.
            KeyValueEntry::make('payload')
                ->label('Детали заявки')
                ->state(fn (ServiceRequest $record): array => $record->payloadLabels())
                ->keyLabel('Параметр')
                ->valueLabel('Значение')
                ->columnSpanFull(),

            TextEntry::make('comment')
                ->label('Комментарий')
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('source')
                ->label('Источник')
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('created_at')
                ->label('Получена')
                ->dateTime('d.m.Y H:i'),
            TextEntry::make('processedBy.name')
                ->label('Обработал')
                ->placeholder('—'),
            TextEntry::make('admin_comment')
                ->label('Комментарий менеджера')
                ->placeholder('—')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Услуга')
                    ->badge()
                    ->formatStateUsing(fn (ServiceType $state): string => $state->label()),
                TextColumn::make('subject_id')
                    ->label('Объект')
                    ->state(fn (ServiceRequest $record): ?string => $record->subjectLabel())
                    ->url(fn (ServiceRequest $record): ?string => $record->subjectUrl())
                    ->openUrlInNewTab()
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Заявитель')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->copyable()
                    ->copyMessage('Телефон скопирован')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_start')
                    ->label('Даты')
                    ->state(fn (ServiceRequest $record): ?string => $record->dateRangeLabel())
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Кол-во')
                    ->placeholder('—')
                    ->tooltip(fn (ServiceRequest $record): string => $record->type->quantityLabel()),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (ServiceRequestStatus $state): string => $state->label())
                    ->color(fn (ServiceRequestStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Получена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('processedBy.name')
                    ->label('Обработал')
                    ->placeholder('—')
                    ->description(fn (ServiceRequest $record): ?string => $record->processed_at?->format('d.m.Y H:i')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Услуга')
                    ->options(ServiceType::options()),
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ServiceRequestStatus::options()),
                Filter::make('new')
                    ->label('Только новые')
                    ->query(fn (Builder $query): Builder => $query->new()),
            ])
            ->emptyStateHeading('Заявок на услуги пока нет')
            ->emptyStateDescription('Здесь появятся запросы из раздела «Услуги».')
            ->recordActions([
                ViewAction::make()
                    ->label('Открыть')
                    ->modalHeading(fn (ServiceRequest $record): string => 'Заявка: '.$record->type->label()),

                Action::make('take')
                    ->label('В работу')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::New)
                    ->requiresConfirmation()
                    ->modalHeading('Взять заявку в работу?')
                    ->action(function (ServiceRequest $record): void {
                        $record->update(['status' => ServiceRequestStatus::InProgress]);

                        Notification::make()
                            ->success()
                            ->title('Заявка взята в работу')
                            ->send();
                    }),

                Action::make('pay')
                    ->label('Оплачена')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    // Оплату отмечает менеджер: эквайринг ещё не подключён (ТЗ п. 4.1).
                    ->visible(fn (ServiceRequest $record): bool => in_array(
                        $record->status,
                        [ServiceRequestStatus::New, ServiceRequestStatus::InProgress],
                        strict: true,
                    ))
                    ->requiresConfirmation()
                    ->modalHeading('Отметить заявку оплаченной?')
                    ->action(function (ServiceRequest $record): void {
                        $record->update(['status' => ServiceRequestStatus::Paid]);

                        Notification::make()
                            ->success()
                            ->title('Заявка отмечена оплаченной')
                            ->send();
                    }),

                Action::make('fulfil')
                    ->label('Услуга оказана')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    // Закрывающий статус оплаченной заявки — для неоплаченных
                    // ту же роль играет «Выполнена».
                    ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::Paid)
                    ->schema([
                        Textarea::make('admin_comment')
                            ->label('Комментарий')
                            ->placeholder('Что сделано по заявке')
                            ->rows(3)
                            ->maxLength(2000),
                    ])
                    ->action(function (ServiceRequest $record, array $data): void {
                        $record->update([
                            'status' => ServiceRequestStatus::Fulfilled,
                            'admin_comment' => $data['admin_comment'] ?? $record->admin_comment,
                            'processed_at' => now(),
                            'processed_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Заявка закрыта: услуга оказана')
                            ->send();
                    }),

                Action::make('complete')
                    ->label('Выполнена')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ServiceRequest $record): bool => ! $record->status->isClosed()
                        && $record->status !== ServiceRequestStatus::Paid)
                    ->schema([
                        Textarea::make('admin_comment')
                            ->label('Комментарий')
                            ->placeholder('Что сделано по заявке')
                            ->rows(3)
                            ->maxLength(2000),
                    ])
                    ->action(function (ServiceRequest $record, array $data): void {
                        $record->update([
                            'status' => ServiceRequestStatus::Done,
                            'admin_comment' => $data['admin_comment'] ?? $record->admin_comment,
                            'processed_at' => now(),
                            'processed_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Заявка отмечена выполненной')
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ServiceRequest $record): bool => ! $record->status->isClosed())
                    ->schema([
                        Textarea::make('admin_comment')
                            ->label('Причина отказа')
                            ->required()
                            ->rows(3)
                            ->maxLength(2000),
                    ])
                    ->action(function (ServiceRequest $record, array $data): void {
                        $record->update([
                            'status' => ServiceRequestStatus::Rejected,
                            'admin_comment' => $data['admin_comment'],
                            'processed_at' => now(),
                            'processed_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Заявка отклонена')
                            ->send();
                    }),

                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Удалить заявку?')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Заявка удалена'),
                    ),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'processedBy', 'subject']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->new()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServiceRequests::route('/'),
        ];
    }
}
