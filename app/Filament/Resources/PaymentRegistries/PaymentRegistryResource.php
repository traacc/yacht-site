<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentRegistries;

use App\Actions\Payment\ConfirmPaymentRegistryAction;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSettlement;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\RestrictsToPaymentRoles;
use App\Filament\Resources\PaymentRegistries\Pages\ManagePaymentRegistries;
use App\Models\PaymentRegistry;
use App\Models\RegattaEntry;
use App\Models\Team;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class PaymentRegistryResource extends Resource
{
    use RestrictsToPaymentRoles;

    protected static ?string $model = PaymentRegistry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['confirmedBy', 'updatedBy'])
            ->withCount('logs');
    }

    public static function getModelLabel(): string
    {
        return 'Реестр платежей';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Реестры платежей';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите название платежа')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->label('Сумма')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->suffix('₽')
                    ->required(),
                Select::make('status')
                    ->label('Статус оплаты')
                    ->options(PaymentStatus::class)
                    ->default(PaymentStatus::Pending)
                    ->required(),
                Select::make('payment_method')
                    ->label('Способ оплаты')
                    ->options(PaymentMethod::class)
                    ->placeholder('Не указан')
                    ->helperText('«Наличные» — платёж, внесённый в кассу; остальные способы учитываются как безналичные.')
                    ->native(false),
                DateTimePicker::make('paid_at')
                    ->label('Дата и время оплаты')
                    ->seconds(false)
                    ->displayFormat('d.m.Y H:i')
                    ->native(false)
                    ->helperText('Для наличных заполняется вручную; при онлайн-оплате проставляется автоматически.'),
                MorphToSelect::make('payable')
                    ->label('Источник платежа')
                    ->types([
                        MorphToSelect\Type::make(Team::class)
                            ->label('Команда (годовой сбор)')
                            ->titleAttribute('name'),
                        MorphToSelect\Type::make(RegattaEntry::class)
                            ->label('Заявка на регату')
                            ->titleAttribute('id')
                            ->getOptionLabelFromRecordUsing(
                                fn (RegattaEntry $record): string => trim(
                                    ($record->team?->name ?? '—').' — '.($record->regatta?->name ?? '—')
                                )
                            ),
                    ])
                    ->columnSpanFull(),
                FileUpload::make('document')
                    ->label('Документ')
                    ->disk('public')
                    ->directory('payment-registries')
                    ->visibility('public')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/avif', 'image/heic', 'image/heif'])
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable()
                    ->summarize(Sum::make()->label('Итого')->money('RUB')),
                TextColumn::make('status')
                    ->label('Статус оплаты')
                    ->badge()
                    ->sortable(),
                IconColumn::make('confirmed')
                    ->label('Приход')
                    ->tooltip('Отметка бухгалтера о фактическом поступлении средств')
                    ->boolean()
                    ->getStateUsing(fn (PaymentRegistry $record): bool => $record->isConfirmed())
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('payment_method')
                    ->label('Способ оплаты')
                    ->badge()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('settlement')
                    ->label('Форма расчёта')
                    ->badge()
                    ->placeholder('—')
                    ->getStateUsing(fn (PaymentRegistry $record): ?PaymentSettlement => $record->settlement())
                    ->toggleable(),
                TextColumn::make('team')
                    ->label('Команда')
                    ->getStateUsing(fn (PaymentRegistry $record): ?string => $record->payableTeam()?->name)
                    ->placeholder('—')
                    ->searchable(false)
                    ->toggleable(),
                TextColumn::make('payable')
                    ->label('Источник')
                    ->getStateUsing(fn (PaymentRegistry $record): string => $record->payableLabel())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('transactions_count')
                    ->label('Транзакции')
                    ->counts('transactions')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->label('Оплачен')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('confirmed_at')
                    ->label('Приход подтверждён')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->description(fn (PaymentRegistry $record): ?string => $record->confirmedBy?->name)
                    ->sortable()
                    ->toggleable(),
                // ТЗ: столбец с тем, кто последним изменил или акцептировал платёж.
                TextColumn::make('updated_by')
                    ->label('Последнее изменение')
                    ->getStateUsing(fn (PaymentRegistry $record): string => $record->lastEditorLabel())
                    ->description(fn (PaymentRegistry $record): ?string => $record->updated_at?->format('d.m.Y H:i'))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Реестров платежей пока нет')
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус оплаты')
                    ->options(PaymentStatus::class),
                SelectFilter::make('payment_method')
                    ->label('Способ оплаты')
                    ->options(PaymentMethod::class),
                SelectFilter::make('settlement')
                    ->label('Форма расчёта')
                    ->options(PaymentSettlement::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereIn(
                            'payment_method',
                            PaymentSettlement::from($data['value'])->methodValues(),
                        )
                        : $query),
                TernaryFilter::make('confirmed')
                    ->label('Приход подтверждён')
                    ->placeholder('Все')
                    ->trueLabel('Подтверждён')
                    ->falseLabel('Не подтверждён')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('confirmed_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('confirmed_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TrashedFilter::make(),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                Action::make('confirmPayment')
                    ->label(fn (PaymentRegistry $record): string => $record->isConfirmed()
                        ? 'Снять подтверждение'
                        : 'Подтвердить приход')
                    ->icon(fn (PaymentRegistry $record): Heroicon => $record->isConfirmed()
                        ? Heroicon::OutlinedXCircle
                        : Heroicon::OutlinedCheckCircle)
                    ->color(fn (PaymentRegistry $record): string => $record->isConfirmed() ? 'gray' : 'success')
                    ->visible(fn (): bool => auth()->user()?->canManagePayments() ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(fn (PaymentRegistry $record): string => $record->isConfirmed()
                        ? 'Снять отметку о приходе?'
                        : 'Подтвердить приход средств?')
                    ->modalDescription(fn (PaymentRegistry $record): string => $record->isConfirmed()
                        ? 'Отметка будет снята, действие попадёт в журнал изменений.'
                        : 'Подтвердите, что средства фактически поступили на счёт или в кассу.')
                    ->action(function (PaymentRegistry $record): void {
                        app(ConfirmPaymentRegistryAction::class)
                            ->handle($record, auth()->user(), ! $record->isConfirmed());

                        Notification::make()
                            ->title('Отметка о приходе обновлена')
                            ->success()
                            ->send();
                    }),
                Action::make('history')
                    ->label('История')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->visible(fn (PaymentRegistry $record): bool => ($record->logs_count ?? 0) > 0)
                    ->modalHeading('История изменений платежа')
                    ->modalContent(fn (PaymentRegistry $record) => view(
                        'filament.resources.payment-registry-logs',
                        ['logs' => $record->logs()->with('user')->latest()->limit(100)->get()],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть'),
                Action::make('transactions')
                    ->label('Транзакции')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->color('gray')
                    ->visible(fn (PaymentRegistry $record): bool => $record->transactions()->exists())
                    ->modalHeading('Транзакции эквайринга')
                    ->modalContent(fn (PaymentRegistry $record) => view(
                        'filament.resources.payment-registry-transactions',
                        ['transactions' => $record->transactions()->latest()->get()],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть'),
                EditAction::make()->modalHeading('Редактировать реестр платежей'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                // Сверка выписки за день: бухгалтер отмечает приход пачкой.
                BulkAction::make('confirmSelected')
                    ->label('Подтвердить приход')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (): bool => auth()->user()?->canManagePayments() ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Подтвердить приход по выбранным платежам?')
                    ->modalDescription('Уже подтверждённые записи будут пропущены. Все действия попадут в журнал изменений.')
                    ->action(function (Collection $records): void {
                        $actor = auth()->user();
                        $action = app(ConfirmPaymentRegistryAction::class);

                        foreach ($records as $record) {
                            $action->handle($record, $actor, true);
                        }

                        Notification::make()
                            ->title('Приход подтверждён')
                            ->body('Обработано записей: '.$records->count())
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentRegistries::route('/'),
        ];
    }
}
