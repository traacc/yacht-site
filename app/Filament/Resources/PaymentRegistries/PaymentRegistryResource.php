<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentRegistries;

use App\Actions\Payment\ConfirmPaymentRegistryAction;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentSettlement;
use App\Enums\PaymentStatus;
use App\Exports\PaymentRegistryExport;
use App\Filament\Concerns\RestrictsToPaymentRoles;
use App\Filament\Resources\PaymentRegistries\Pages\ManagePaymentRegistries;
use App\Models\PaymentRegistry;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Scopes\OwnedScope;
use App\Models\Team;
use App\Models\Yacht;
use App\Services\MembershipFees;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class PaymentRegistryResource extends Resource
{
    use RestrictsToPaymentRoles;

    protected static ?string $model = PaymentRegistry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    protected static ?int $navigationSort = 20;

    /** Предохранитель: PhpSpreadsheet держит весь лист в памяти. */
    private const EXPORT_ROW_LIMIT = 20000;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['confirmedBy', 'updatedBy', 'regatta', 'yacht', 'team'])
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
                    ->required()
                    // Подсказка со ставкой членского взноса: сумму бухгалтер вносит
                    // руками (нал, доплаты, прошлые годы), поэтому не подставляем.
                    ->helperText(static function (Get $get): ?string {
                        $purpose = $get('purpose');
                        $purpose = $purpose instanceof PaymentPurpose
                            ? $purpose
                            : PaymentPurpose::tryFrom((string) $purpose);

                        if ($purpose !== PaymentPurpose::MembershipFee) {
                            return null;
                        }

                        $fees = app(MembershipFees::class);
                        $rate = $fees->current();

                        return $rate === null
                            ? 'Размер членского взноса не задан — раздел «Сайт → Правила вступления».'
                            : 'Членский взнос на '.$rate['year'].' год — '.$rate['formatted'].' ('.$fees->unit().').';
                    }),
                Select::make('purpose')
                    ->label('Назначение платежа')
                    ->options(PaymentPurpose::class)
                    ->placeholder('Не указано')
                    ->native(false)
                    // Нужен для подсказки со ставкой взноса в поле «Сумма».
                    ->live(),
                TextInput::make('payer_name')
                    ->label('Плательщик (ФИО)')
                    ->maxLength(255)
                    ->helperText('Заполняется автоматически по команде или заявке; можно уточнить вручную.'),
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
                    ->live()
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
                // Ручная привязка — только для платежей без источника (наличные,
                // спонсорские и т.п.). Если источник выбран, значения проставит
                // SyncPaymentRegistryLinksAction, и поля скрываются.
                Select::make('regatta_id')
                    ->label('Регата')
                    ->options(fn (): array => Regatta::query()
                        ->orderByDesc('date_start')
                        ->limit(300)
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->placeholder('Не привязан')
                    ->visible(fn (Get $get): bool => blank($get('payable_type'))),
                Select::make('yacht_id')
                    ->label('Яхта')
                    ->options(fn (): array => Yacht::query()
                        ->withoutGlobalScope(OwnedScope::class)
                        ->orderBy('name')
                        ->limit(500)
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->placeholder('Не привязана')
                    ->visible(fn (Get $get): bool => blank($get('payable_type'))),
                Select::make('team_id')
                    ->label('Команда')
                    ->options(fn (): array => Team::query()
                        ->orderBy('name')
                        ->limit(500)
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->placeholder('Не привязана')
                    ->visible(fn (Get $get): bool => blank($get('payable_type'))),
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
                    // Оба суммаризатора без ->query() и не на relationship-колонке,
                    // поэтому итоги по всем группам считаются одним запросом.
                    ->summarize([
                        Count::make()->label('Платежей'),
                        Sum::make()->label('Итого')->money('RUB'),
                    ]),
                TextColumn::make('purpose')
                    ->label('Назначение')
                    ->badge()
                    ->placeholder('Не указано')
                    ->sortable()
                    ->toggleable(),
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
                TextColumn::make('payer_name')
                    ->label('Плательщик')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('team.name')
                    ->label('Команда')
                    ->placeholder('—')
                    // Поиск по связи дал бы whereHas и отрезал мягко удалённые записи.
                    ->searchable(false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('yacht.name')
                    ->label('Яхта')
                    ->description(fn (PaymentRegistry $record): ?string => $record->yacht?->vfps_number)
                    ->placeholder('—')
                    ->searchable(false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->placeholder('—')
                    ->searchable(false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('payable')
                    ->label('Источник')
                    ->getStateUsing(fn (PaymentRegistry $record): string => $record->payableLabel())
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->groups(static::groups())
            ->groupingSettingsInDropdownOnDesktop()
            // Режим сводки: только строки итогов по группам (см. фильтр «pivot»).
            ->groupsOnly(fn ($livewire): bool => (bool) data_get($livewire->tableFilters, 'pivot.only_totals', false))
            ->filters([
                // Опции задаём через options(), а не relationship(): последний
                // применяет whereHas и отрезал бы мягко удалённые записи.
                SelectFilter::make('regatta_id')
                    ->label('Регата')
                    ->options(fn (): array => Regatta::withTrashed()
                        ->orderByDesc('date_start')
                        ->limit(300)
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('yacht_id')
                    ->label('Яхта')
                    // Без снятия OwnedScope половина яхт в списке не появится.
                    ->options(fn (): array => Yacht::withoutGlobalScope(OwnedScope::class)
                        ->withTrashed()
                        ->orderBy('name')
                        ->limit(500)
                        ->get()
                        ->mapWithKeys(fn (Yacht $yacht): array => [
                            $yacht->id => $yacht->vfps_number
                                ? "{$yacht->name} ({$yacht->vfps_number})"
                                : (string) $yacht->name,
                        ])
                        ->all())
                    ->searchable()
                    ->multiple()
                    ->optionsLimit(50),
                SelectFilter::make('team_id')
                    ->label('Команда')
                    ->options(fn (): array => Team::withTrashed()
                        ->orderBy('name')
                        ->limit(500)
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('purpose')
                    ->label('Назначение платежа')
                    ->options(PaymentPurpose::class)
                    ->multiple(),
                Filter::make('paid_period')
                    ->label('Период оплаты')
                    ->schema([
                        DatePicker::make('from')->label('С')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('По')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Оплата с '.Carbon::parse($data['from'])->format('d.m.Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Оплата по '.Carbon::parse($data['until'])->format('d.m.Y');
                        }

                        return $indicators;
                    }),
                Filter::make('amount_range')
                    ->label('Сумма')
                    ->schema([
                        TextInput::make('from')->label('От')->numeric()->minValue(0),
                        TextInput::make('until')->label('До')->numeric()->minValue(0),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $v) => $q->where('amount', '>=', $v))
                        ->when($data['until'] ?? null, fn (Builder $q, $v) => $q->where('amount', '<=', $v)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Сумма от '.number_format((float) $data['from'], 2, ',', ' ').' ₽';
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Сумма до '.number_format((float) $data['until'], 2, ',', ' ').' ₽';
                        }

                        return $indicators;
                    }),
                Filter::make('payer')
                    ->label('Плательщик')
                    ->schema([
                        TextInput::make('name')->label('ФИО содержит')->maxLength(255),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['name'] ?? null, fn (Builder $q, $v) => $q->where('payer_name', 'like', "%{$v}%")))
                    ->indicateUsing(fn (array $data): array => filled($data['name'] ?? null)
                        ? ['Плательщик: '.$data['name']]
                        : []),
                SelectFilter::make('status')
                    ->label('Статус оплаты')
                    ->options(PaymentStatus::class)
                    ->multiple(),
                SelectFilter::make('payment_method')
                    ->label('Способ оплаты')
                    ->options(PaymentMethod::class)
                    ->multiple(),
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
                // Сам ничего не фильтрует — читается в groupsOnly().
                Filter::make('pivot')
                    ->label('Режим сводки')
                    ->schema([
                        Toggle::make('only_totals')
                            ->label('Только итоги по группам')
                            ->helperText('Показывать одни строки итогов. Пагинация отключается — сначала сузьте выборку фильтрами.'),
                    ])
                    ->query(fn (Builder $query): Builder => $query)
                    ->indicateUsing(fn (array $data): array => ($data['only_totals'] ?? false)
                        ? ['Только итоги по группам']
                        : []),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->headerActions([
                Action::make('exportXlsx')
                    ->label('Экспорт в Excel')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('white')
                    ->visible(fn (): bool => auth()->user()?->canManagePayments() ?? false)
                    ->action(function ($livewire) {
                        // visible() прячет кнопку, но не защищает сам вызов метода.
                        abort_unless(auth()->user()?->canManagePayments() ?? false, 403);

                        $query = $livewire->getFilteredSortedTableQuery();

                        if ($query->clone()->count() > self::EXPORT_ROW_LIMIT) {
                            Notification::make()
                                ->title('Слишком много записей')
                                ->body('Сузьте выборку фильтрами: за один раз выгружается не более '.self::EXPORT_ROW_LIMIT.' строк.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        $group = $livewire->getTableGrouping();

                        return (new PaymentRegistryExport)->download(
                            query: $query,
                            groupKeyUsing: $group ? fn (PaymentRegistry $record) => $group->getStringKey($record) : null,
                            groupTitleUsing: $group ? fn (PaymentRegistry $record) => (string) $group->getTitle($record) : null,
                            groupLabel: $group ? (string) $group->getLabel() : null,
                            filename: 'payments_'.now()->format('Y-m-d').'.xlsx',
                        );
                    }),
            ])
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

    /**
     * Доступные группировки с автоматическим суммированием.
     *
     * Группируем по собственным колонкам таблицы, а не по связям: группировка
     * по relationship тянет join/whereHas, который протащил бы OwnedScope у Yacht
     * и SoftDeletes — платежи по бесхозным яхтам и удалённым регатам молча
     * выпали бы из групп и итогов.
     *
     * @return list<Group>
     */
    protected static function groups(): array
    {
        return [
            Group::make('purpose')
                ->label('Назначение платежа')
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->purposeLabel())
                ->collapsible(),

            Group::make('payment_method')
                ->label('Способ оплаты')
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->payment_method?->label() ?? 'Не указан')
                ->collapsible(),

            // Виртуальная группа: колонки «форма расчёта» в БД нет, категория
            // выводится из способа оплаты. Ключ записи обязан совпадать
            // со значением CASE побайтово, иначе итог группы не найдётся.
            Group::make('settlement')
                ->label('Форма расчёта')
                ->column('payment_method')
                ->groupQueryUsing(fn (QueryBuilder $query): QueryBuilder => $query->groupByRaw(
                    "case when payment_method is null then null when payment_method = 'cash' then 'cash' else 'cashless' end"
                ))
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                    "case when payment_method = 'cash' then 0 else 1 end {$direction}"
                ))
                ->getKeyFromRecordUsing(fn (PaymentRegistry $record): ?string => $record->settlement()?->value)
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->settlement()?->label() ?? 'Не указана')
                ->collapsible(),

            Group::make('status')
                ->label('Статус оплаты')
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->status?->label() ?? '—')
                ->collapsible(),

            Group::make('regatta')
                ->label('Регата')
                ->column('regatta_id')
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->regattaLabel())
                // Без этого группы упорядочились бы по UUID.
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Regatta::withTrashed()->select('name')
                        ->whereColumn('regattas.id', 'payment_registries.regatta_id')
                        ->limit(1),
                    $direction,
                ))
                ->collapsible(),

            Group::make('yacht')
                ->label('Яхта')
                ->column('yacht_id')
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->yachtLabel())
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Yacht::withoutGlobalScope(OwnedScope::class)->withTrashed()->select('name')
                        ->whereColumn('yachts.id', 'payment_registries.yacht_id')
                        ->limit(1),
                    $direction,
                ))
                ->collapsible(),

            Group::make('team')
                ->label('Команда')
                ->column('team_id')
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->teamLabel())
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Team::withTrashed()->select('name')
                        ->whereColumn('teams.id', 'payment_registries.team_id')
                        ->limit(1),
                    $direction,
                ))
                ->collapsible(),

            Group::make('payer_name')
                ->label('Плательщик')
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->payer_name ?: 'Не указан')
                ->collapsible(),

            Group::make('paid_date')
                ->label('Дата оплаты')
                ->column('paid_at')
                ->date()
                ->collapsible(),

            Group::make('paid_month')
                ->label('Месяц оплаты')
                ->column('paid_at')
                ->groupQueryUsing(fn (QueryBuilder $query): QueryBuilder => $query->groupByRaw("date_format(paid_at, '%Y-%m')"))
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw("date_format(paid_at, '%Y-%m') {$direction}"))
                ->getKeyFromRecordUsing(fn (PaymentRegistry $record): ?string => $record->paid_at?->format('Y-m'))
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->paid_at?->translatedFormat('F Y') ?? 'Без даты оплаты')
                ->collapsible(),

            Group::make('source_type')
                ->label('Тип источника')
                ->column('payable_type')
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => match (class_basename((string) $record->payable_type)) {
                    'RegattaEntry' => 'Заявка на регату',
                    'Team' => 'Команда',
                    default => 'Без источника',
                })
                ->collapsible(),

            Group::make('confirmed')
                ->label('Подтверждение прихода')
                ->column('confirmed_at')
                ->groupQueryUsing(fn (QueryBuilder $query): QueryBuilder => $query->groupByRaw('confirmed_at is not null'))
                ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw("confirmed_at is not null {$direction}"))
                ->getKeyFromRecordUsing(fn (PaymentRegistry $record): string => $record->isConfirmed() ? '1' : '0')
                ->getTitleFromRecordUsing(fn (PaymentRegistry $record): string => $record->isConfirmed()
                    ? 'Приход подтверждён'
                    : 'Не подтверждён')
                ->collapsible(),
        ];
    }
}
