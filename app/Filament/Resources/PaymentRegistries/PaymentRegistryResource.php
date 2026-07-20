<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentRegistries;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\PaymentRegistries\Pages\ManagePaymentRegistries;
use App\Models\PaymentRegistry;
use App\Models\RegattaEntry;
use App\Models\Team;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentRegistryResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = PaymentRegistry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 20;

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
                    ->native(false),
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
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
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
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус оплаты')
                    ->badge()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Способ оплаты')
                    ->badge()
                    ->placeholder('—')
                    ->sortable()
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
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать реестр платежей'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
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
