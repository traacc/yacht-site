<?php

declare(strict_types=1);

namespace App\Filament\Resources\PendingRegattaEntries;

use App\Filament\Resources\PendingRegattaEntries\Pages\ManagePendingRegattaEntries;
use App\Models\RegattaEntry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PendingRegattaEntryResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = RegattaEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Одобрение заявок';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'Заявка на рассмотрении';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на рассмотрении';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'pending')
            ->whereHas('regatta', fn (Builder $q) => $q->whereIn(
                'regatta_status',
                [
                    \App\Enums\RegattaStatus::Closest->value,
                    \App\Enums\RegattaStatus::Upcoming->value,
                    \App\Enums\RegattaStatus::Active->value,
                ],
            ))
            ->orderBy(
                \App\Models\Regatta::select('date_start')
                    ->whereColumn('regattas.id', 'regatta_entries.regatta_id'),
                'asc'
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('regatta.name')
                ->label('Регата'),
            TextEntry::make('team.name')
                ->label('Команда'),
            TextEntry::make('yacht.name')
                ->label('Яхта')
                ->placeholder('—'),
            TextEntry::make('created_at')
                ->label('Дата подачи')
                ->dateTime('d.m.Y H:i'),

            RepeatableEntry::make('crew')
                ->label('Экипаж')
                ->schema([
                    TextEntry::make('teamMember.user.name')
                        ->label('Участник'),
                    TextEntry::make('role')
                        ->label('Роль')
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'main'    => 'Основной',
                            'reserve' => 'Запасной',
                            'captain' => 'Рулевой',
                            default   => $state,
                        }),
                ])
                ->columns(2)
                ->columnSpanFull(),

            RepeatableEntry::make('documents')
                ->label('Документы')
                ->schema([
                    TextEntry::make('title')
                        ->label('Документ')
                        ->url(fn (\App\Models\Document $record): string => $record->file_url)
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
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('team.name')
                    ->label('Команда')
                    ->searchable(),
                TextColumn::make('captain')
                    ->label('Рулевой')
                    ->state(fn (RegattaEntry $record): string => $record->crew()
                        ->where('role', 'captain')
                        ->first()?->teamMember?->user?->name ?? '—'
                    ),
                TextColumn::make('crew_count')
                    ->label('Экипаж')
                    ->state(fn (RegattaEntry $record): string => (string) $record->crew()
                        ->whereIn('role', ['main', 'reserve', 'captain'])
                        ->count()
                    ),
                TextColumn::make('created_at')
                    ->label('Подана')
                    ->dateTime('d M Y'),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Нет заявок на рассмотрении')
            ->emptyStateDescription('Все заявки обработаны.')
            ->filters([])
            ->recordActions([
                ViewAction::make()
                    ->label('Просмотр')
                    ->modalHeading(fn (RegattaEntry $record): string =>
                        "Заявка: {$record->team?->name} — {$record->regatta?->name}"
                    )
                    ->extraModalFooterActions([
                        Action::make('approveFromView')
                            ->label('Одобрить')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->action(function (RegattaEntry $record): void {
                                $record->approve();
                                Notification::make()
                                    ->title('Заявка одобрена')
                                    ->success()
                                    ->send();
                            })
                            ->cancelParentActions(),
                        Action::make('rejectFromView')
                            ->label('Отклонить')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->action(function (RegattaEntry $record): void {
                                $record->reject();
                                Notification::make()
                                    ->title('Заявка отклонена')
                                    ->danger()
                                    ->send();
                            })
                            ->cancelParentActions(),
                    ]),
                Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Одобрить заявку?')
                    ->modalDescription(fn (RegattaEntry $record): string =>
                        "Команда «{$record->team?->name}» на регату «{$record->regatta?->name}»"
                    )
                    ->modalSubmitActionLabel('Одобрить')
                    ->action(function (RegattaEntry $record): void {
                        $record->approve();
                        Notification::make()
                            ->title('Заявка одобрена')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Отклонить заявку?')
                    ->modalDescription(fn (RegattaEntry $record): string =>
                        "Команда «{$record->team?->name}» на регату «{$record->regatta?->name}»"
                    )
                    ->modalSubmitActionLabel('Отклонить')
                    ->action(function (RegattaEntry $record): void {
                        $record->reject();
                        Notification::make()
                            ->title('Заявка отклонена')
                            ->danger()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveAll')
                        ->label('Одобрить выбранные')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Одобрить выбранные заявки?')
                        ->modalSubmitActionLabel('Одобрить')
                        ->action(function (Collection $records): void {
                            $records->each->approve();
                            Notification::make()
                                ->title('Заявки одобрены')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('rejectAll')
                        ->label('Отклонить выбранные')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Отклонить выбранные заявки?')
                        ->modalSubmitActionLabel('Отклонить')
                        ->action(function (Collection $records): void {
                            $records->each->reject();
                            Notification::make()
                                ->title('Заявки отклонены')
                                ->danger()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePendingRegattaEntries::route('/'),
        ];
    }
}
