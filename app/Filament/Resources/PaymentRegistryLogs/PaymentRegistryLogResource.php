<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentRegistryLogs;

use App\Enums\PaymentRegistryLogEvent;
use App\Exports\PaymentRegistryLogExport;
use App\Filament\Concerns\RestrictsToPaymentRoles;
use App\Filament\Resources\PaymentRegistryLogs\Pages\ManagePaymentRegistryLogs;
use App\Models\PaymentRegistry;
use App\Models\PaymentRegistryLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Журнал изменений реестра платежей — только для чтения.
 * Записи создаёт App\Services\PaymentRegistryLogger.
 */
class PaymentRegistryLogResource extends Resource
{
    use RestrictsToPaymentRoles;

    protected static ?string $model = PaymentRegistryLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    protected static ?int $navigationSort = 21;

    public static function getModelLabel(): string
    {
        return 'Запись журнала';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Журнал изменений';
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата и время')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('registry_name')
                    ->label('Платёж')
                    ->searchable()
                    ->wrap()
                    ->description(fn (PaymentRegistryLog $record): ?string => $record->registry_amount !== null
                        ? number_format((float) $record->registry_amount, 2, ',', ' ').' ₽'
                        : null),
                TextColumn::make('event')
                    ->label('Событие')
                    ->badge()
                    ->sortable(),
                TextColumn::make('actor')
                    ->label('Кто')
                    ->getStateUsing(fn (PaymentRegistryLog $record): string => $record->actorLabel())
                    ->searchable(['actor_name']),
                TextColumn::make('changed_fields')
                    ->label('Что изменено')
                    ->getStateUsing(fn (PaymentRegistryLog $record): string => $record->changesText())
                    ->placeholder('—')
                    ->wrap()
                    ->limit(120)
                    ->tooltip(fn (PaymentRegistryLog $record): ?string => $record->changesText() ?: null),
                TextColumn::make('ip')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Записей в журнале пока нет')
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Событие')
                    ->options(PaymentRegistryLogEvent::class),
                SelectFilter::make('user_id')
                    ->label('Пользователь')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_registry_id')
                    ->label('Платёж')
                    ->options(fn (): array => PaymentRegistry::query()
                        ->withTrashed()
                        ->latest()
                        ->limit(200)
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                Filter::make('period')
                    ->label('Период')
                    ->schema([
                        DatePicker::make('from')->label('С')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('По')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'С '.Carbon::parse($data['from'])->format('d.m.Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'По '.Carbon::parse($data['until'])->format('d.m.Y');
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->deferFilters(false)
            ->headerActions([
                Action::make('exportXlsx')
                    ->label('Экспорт в Excel')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('white')
                    ->visible(fn (): bool => auth()->user()?->canManagePayments() ?? false)
                    ->action(function ($livewire) {
                        // visible() прячет кнопку, но не защищает сам вызов метода.
                        abort_unless(auth()->user()?->canManagePayments() ?? false, 403);

                        return (new PaymentRegistryLogExport)->download(
                            $livewire->getFilteredSortedTableQuery(),
                            'payment_registry_log_'.now()->format('Y-m-d').'.xlsx',
                        );
                    }),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Подробнее')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading('Запись журнала изменений')
                    ->modalContent(fn (PaymentRegistryLog $record) => view(
                        'filament.resources.payment-registry-log-details',
                        ['log' => $record],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть'),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentRegistryLogs::route('/'),
        ];
    }
}
