<?php

declare(strict_types=1);

namespace App\Filament\Resources\YachtOwnershipTransfers;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\YachtOwnershipTransfers\Pages\ManageYachtOwnershipTransfers;
use App\Models\Document;
use App\Models\YachtOwnershipTransfer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class YachtOwnershipTransferResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = YachtOwnershipTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Передача яхт';

    protected static ?int $navigationSort = 8;

    protected static string|UnitEnum|null $navigationGroup = 'Яхты';

    public static function getModelLabel(): string
    {
        return 'Заявка на передачу яхты';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Передача яхт';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'pending')
            ->latest();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('yacht.name')
                ->label('Яхта')
                ->placeholder('—'),
            TextEntry::make('yacht.vfps_number')
                ->label('Номер ВФПС')
                ->placeholder('—'),
            TextEntry::make('requester.name')
                ->label('Заявитель'),
            TextEntry::make('previousOwner.name')
                ->label('Текущий владелец')
                ->placeholder('—'),
            TextEntry::make('created_at')
                ->label('Дата подачи')
                ->dateTime('d.m.Y H:i'),
            TextEntry::make('comment')
                ->label('Комментарий заявителя')
                ->columnSpanFull()
                ->placeholder('—'),

            RepeatableEntry::make('documents')
                ->label('Документы, подтверждающие владение')
                ->schema([
                    TextEntry::make('title')
                        ->label('Документ')
                        ->url(fn (Document $record): string => $record->file_url)
                        ->openUrlInNewTab(),
                    TextEntry::make('file_size_for_humans')
                        ->label('Размер'),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->placeholder('Документы не прикреплены'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('yacht.name')
                    ->label('Яхта')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('yacht.vfps_number')
                    ->label('Номер ВФПС')
                    ->searchable(),
                TextColumn::make('requester.name')
                    ->label('Заявитель')
                    ->searchable(),
                TextColumn::make('previousOwner.name')
                    ->label('Текущий владелец')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Подана')
                    ->dateTime('d.m.Y'),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Нет заявок на рассмотрении')
            ->emptyStateDescription('Все заявки на передачу яхт обработаны.')
            ->recordActions([
                ViewAction::make()
                    ->label('Просмотр')
                    ->modalHeading(fn (YachtOwnershipTransfer $record): string => "Передача яхты «{$record->yacht?->name}» → {$record->requester?->name}"
                    )
                    ->extraModalFooterActions([
                        Action::make('approveFromView')
                            ->label('Одобрить')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->action(function (YachtOwnershipTransfer $record): void {
                                $record->approve();
                                static::approvedNotification();
                            })
                            ->cancelParentActions(),
                        Action::make('rejectFromView')
                            ->label('Отклонить')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->schema(static::rejectForm())
                            ->action(function (YachtOwnershipTransfer $record, array $data): void {
                                $record->reject($data['rejection_reason'] ?? null);
                                static::rejectedNotification();
                            })
                            ->cancelParentActions(),
                    ]),
                Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Одобрить передачу яхты?')
                    ->modalDescription(fn (YachtOwnershipTransfer $record): string => "Яхта «{$record->yacht?->name}» будет передана пользователю «{$record->requester?->name}»."
                    )
                    ->modalSubmitActionLabel('Одобрить')
                    ->action(function (YachtOwnershipTransfer $record): void {
                        $record->approve();
                        static::approvedNotification();
                    }),
                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Отклонить заявку?')
                    ->schema(static::rejectForm())
                    ->modalSubmitActionLabel('Отклонить')
                    ->action(function (YachtOwnershipTransfer $record, array $data): void {
                        $record->reject($data['rejection_reason'] ?? null);
                        static::rejectedNotification();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageYachtOwnershipTransfers::route('/'),
        ];
    }

    /**
     * @return array<int, Field>
     */
    protected static function rejectForm(): array
    {
        return [
            Textarea::make('rejection_reason')
                ->label('Причина отказа')
                ->placeholder('Будет видна заявителю (необязательно)')
                ->rows(3),
        ];
    }

    protected static function approvedNotification(): void
    {
        Notification::make()
            ->title('Яхта передана новому владельцу')
            ->success()
            ->send();
    }

    protected static function rejectedNotification(): void
    {
        Notification::make()
            ->title('Заявка отклонена')
            ->danger()
            ->send();
    }
}
