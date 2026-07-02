<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RentalRequests;

use App\Enums\RentalRequestStatus;
use App\Filament\User\Resources\RentalRequests\Pages\ManageRentalRequests;
use App\Models\YachtRentalRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RentalRequestResource extends Resource
{
    protected static ?string $model = YachtRentalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'Заявка на аренду';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на аренду';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Models\Yacht::where('user_id', auth()->id())->exists();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('yacht.name')
                    ->label('Яхта')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Заявитель')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->copyable()
                    ->copyMessage('Телефон скопирован'),
                TextColumn::make('desired_date')
                    ->label('Желаемая дата')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('comment')
                    ->label('Комментарий')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (YachtRentalRequest $record): ?string => $record->comment),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (RentalRequestStatus $state): string => $state->label())
                    ->color(fn (RentalRequestStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Получена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Заявок на аренду пока нет')
            ->emptyStateDescription('Здесь появятся запросы на аренду ваших яхт, отправленные через каталог.')
            ->recordActions([
                Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (YachtRentalRequest $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Одобрить заявку на аренду?')
                    ->modalDescription(fn (YachtRentalRequest $record): string => 'Заявитель: '.$record->name.'. Свяжитесь с ним для согласования деталей.')
                    ->action(function (YachtRentalRequest $record): void {
                        $record->update(['status' => RentalRequestStatus::Approved]);
                        Notification::make()
                            ->success()
                            ->title('Заявка одобрена')
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (YachtRentalRequest $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Отклонить заявку на аренду?')
                    ->action(function (YachtRentalRequest $record): void {
                        $record->update(['status' => RentalRequestStatus::Rejected]);
                        Notification::make()
                            ->success()
                            ->title('Заявка отклонена')
                            ->send();
                    }),
                DeleteAction::make()
                    ->label('Удалить')
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
        return parent::getEloquentQuery()
            ->whereHas('yacht', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->with('yacht')
            ->latest();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRentalRequests::route('/'),
        ];
    }
}
