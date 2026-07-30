<?php

declare(strict_types=1);

namespace App\Filament\Resources\RepairRequests;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\RepairRequests\Pages\ManageRepairRequests;
use App\Models\RepairRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Заявки по кнопке «Хотите такой ремонт?» (раздел «Carter 30»).
 *
 * Создавать заявки из админки нельзя — они приходят только с сайта.
 */
class RepairRequestResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = RepairRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Заявки на ремонт';

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'Заявка на ремонт';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на ремонт';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('repairCase.title')
                    ->label('Кейс')
                    ->placeholder('Обзорная страница')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('name')
                    ->label('Заявитель')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->copyable()
                    ->copyMessage('Телефон скопирован'),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('comment')
                    ->label('Комментарий')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (RepairRequest $record): ?string => $record->comment),
                TextColumn::make('created_at')
                    ->label('Получена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('processedBy.name')
                    ->label('Обработал')
                    ->placeholder('—')
                    ->description(fn (RepairRequest $record): ?string => $record->processed_at?->format('d.m.Y H:i')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('pending')
                    ->label('Только необработанные')
                    ->query(fn (Builder $query): Builder => $query->pending()),
            ])
            ->emptyStateHeading('Заявок на ремонт пока нет')
            ->emptyStateDescription('Здесь появятся запросы по кнопке «Хотите такой ремонт?».')
            ->recordActions([
                Action::make('process')
                    ->label('Обработана')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (RepairRequest $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Отметить заявку обработанной?')
                    ->modalDescription(fn (RepairRequest $record): string => 'Заявитель: '.$record->name.', '.$record->phone.'.')
                    ->action(function (RepairRequest $record): void {
                        $record->update([
                            'processed_at' => now(),
                            'processed_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Заявка отмечена обработанной')
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
        return parent::getEloquentQuery()->with(['repairCase', 'processedBy']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereNull('processed_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRepairRequests::route('/'),
        ];
    }
}
