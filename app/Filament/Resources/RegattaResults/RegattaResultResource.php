<?php

namespace App\Filament\Resources\RegattaResults;

use App\Actions\RegattaResult\ImportRegattaResultItemsAction;
use App\Filament\Resources\RegattaResults\Pages\ManageRegattaResults;
use App\Models\RegattaEntry;
use App\Models\RegattaResult;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Illuminate\Database\Eloquent\Builder;

class RegattaResultResource extends Resource
{
    protected static ?string $model = RegattaResult::class;

    protected static string|BackedEnum|null $navigationIcon = 'cup';

    protected static ?int $navigationSort = 2;


    public static function getModelLabel(): string
    {
        return 'Результат регаты';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Результаты регат';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('regatta_id')
                    ->label('Регата')
                    ->relationship(
                        name: 'regatta',
                        titleAttribute:'name',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('date_end', 'desc'),
                    )
                    ->required()
                    ->columnSpanFull(),

                Select::make('result_type')
                    ->label('Тип результата')
                    ->options([
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                    ])
                    ->required()
                    ->default('preliminary'),

                FileUpload::make('pdf_path')
                    ->label('PDF файл')
                    ->directory('documents')
                    ->disk('public')
                    ->preserveFilenames(),

                Select::make('source')
                    ->label('Источник')
                    ->options([
                        'manual'   => 'Вручную',
                        'imported' => 'Импортирован',
                    ])
                    ->required()
                    ->default('manual'),

                Repeater::make('items')
                    ->label('Результаты участников')
                    ->relationship('items')
                    ->schema([
                        Select::make('team_id')
                            ->label('Команда')
                            ->relationship('team', 'name')
                            ->required()
                            ->columnSpan(2),

                        Select::make('yacht_id')
                            ->label('Яхта')
                            ->relationship('yacht', 'name')
                            ->nullable()
                            ->columnSpan(2),

                        TextInput::make('total_points')
                            ->label('Очки')
                            ->numeric()
                            ->required()
                            ->default(0.0),

                        TextInput::make('final_position')
                            ->label('Место')
                            ->numeric()
                            ->nullable(),
                    ])
                    ->columns(6)
                    ->addActionLabel('Добавить участника')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Альтернативный вариант редактирования участников — компактная таблица.
     */
    public static function itemsTableSchema(): Repeater
    {
        return Repeater::make('items')
            ->label('Результаты участников')
            ->relationship('items')
            ->table([
                TableColumn::make('Место')->markAsRequired(false),
                TableColumn::make('Команда'),
                TableColumn::make('Яхта')->markAsRequired(false),
                TableColumn::make('Очки'),
            ])
            ->schema([

                Select::make('team_id')
                    ->label('Команда')
                    ->relationship('team', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Select $component): void {
                        if (blank($state)) {
                            return;
                        }

                        $regattaId = $component->getParentRepeater()?->getRecord()?->regatta_id;

                        if (blank($regattaId)) {
                            return;
                        }

                        $yachtId = RegattaEntry::query()
                            ->where('regatta_id', $regattaId)
                            ->where('team_id', $state)
                            ->value('yacht_id');

                        if (filled($yachtId)) {
                            $set('yacht_id', $yachtId);
                        }
                    }),

                Select::make('yacht_id')
                    ->label('Яхта')
                    ->relationship('yacht', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('total_points')
                    ->label('Очки')
                    ->numeric()
                    ->required()
                    ->default(0.0),
                TextInput::make('final_position')
                    ->label('Место')
                    ->numeric()
                    ->nullable(),
            ])
            ->defaultItems(0)
            ->addActionLabel('Добавить участника')
            ->columnSpanFull();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('regatta.name')
                    ->label('Регата'),
                TextEntry::make('result_type')
                    ->label('Тип')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                        default       => $state,
                    }),
                TextEntry::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'manual'   => 'Вручную',
                        'imported' => 'Импортирован',
                        default    => $state,
                    }),
                TextEntry::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),

                RepeatableEntry::make('items')
                    ->label('Результаты участников')
                    ->schema([
                        TextEntry::make('final_position')
                            ->label('Место')
                            ->placeholder('-'),
                        TextEntry::make('team.name')
                            ->label('Команда'),
                        TextEntry::make('yacht.name')
                            ->label('Яхта')
                            ->placeholder('-'),
                        TextEntry::make('total_points')
                            ->label('Очки')
                            ->numeric(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('regatta.season.year')
                    ->label('Сезон')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('regatta.dateRange')
                    ->label('Дата регаты')->getStateUsing(fn ($record) => $record->regatta?->dateRange()),
                TextColumn::make('result_type')
                    ->label('Тип результатов')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                        default       => $state,
                    }),
                TextColumn::make('source')
                    ->label('Формат')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'manual'   => 'Вручную',
                        'imported' => 'Импортирован',
                        default    => $state,
                    }),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->modalHeading('Редактировать результат регаты'),
                EditAction::make('edit_table')
                    ->label('Редактировать таблицей')
                    ->icon(Heroicon::TableCells)
                    ->modalHeading('Редактировать результат регаты (таблица)')
                    ->modalWidth('7xl')
                    ->schema([
                        self::itemsTableSchema(),
                    ]),
                Action::make('import_csv')
                    ->label('Импорт CSV')
                    ->icon(Heroicon::ArrowUpTray)
                    ->color('success')
                    ->form([
                        FileUpload::make('csv_file')
                            ->label('CSV-файл')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->disk('local')
                            ->directory('csv-imports')
                            ->required(),
                        Checkbox::make('replace')
                            ->label('Заменить существующие записи')
                            ->default(false)
                            ->helperText('Если включено — все текущие items будут удалены перед импортом'),
                    ])
                    ->action(function (RegattaResult $record, array $data): void {
                        $path    = Storage::disk('local')->path($data['csv_file']);
                        $content = file_get_contents($path);
                        Storage::disk('local')->delete($data['csv_file']);

                        try {
                            $result = app(ImportRegattaResultItemsAction::class)
                                ->execute($record, $content, (bool) ($data['replace'] ?? false));
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Ошибка импорта')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            return;
                        }

                        $body = "Импортировано: {$result['imported']}, пропущено: {$result['skipped']}";
                        if (! empty($result['errors'])) {
                            $body .= "\n\nОшибки:\n" . implode("\n", $result['errors']);
                        }

                        Notification::make()
                            ->title('Импорт завершён')
                            ->body($body)
                            ->when(empty($result['errors']), fn($n) => $n->success())
                            ->when(! empty($result['errors']), fn($n) => $n->warning())
                            ->send();
                    }),
                DeleteAction::make(),
                Action::make('export_csv')
                    ->label('Экспорт CSV')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('gray')
                    ->action(function (RegattaResult $record): StreamedResponse {
                        $filename = sprintf(
                            'result_%s_%s.csv',
                            str($record->regatta?->name ?? $record->id)->slug(),
                            now()->format('Y-m-d'),
                        );

                        return response()->streamDownload(function () use ($record): void {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF"); // BOM для Excel
                            fputcsv($handle, ['Место', 'Команда', 'Яхта', 'Очки'], ';');

                            foreach ($record->items as $item) {
                                fputcsv($handle, [
                                    $item->final_position ?? '',
                                    $item->team?->name ?? '',
                                    $item->yacht?->name ?? '',
                                    $item->total_points,
                                ], ';');
                            }

                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('export_csv_bulk')
                        ->label('Экспорт CSV')
                        ->icon(Heroicon::ArrowDownTray)
                        ->action(function (Collection $records): StreamedResponse {
                            $filename = sprintf('results_export_%s.csv', now()->format('Y-m-d'));

                            return response()->streamDownload(function () use ($records): void {
                                $handle = fopen('php://output', 'w');
                                fputs($handle, "\xEF\xBB\xBF"); // BOM для Excel
                                fputcsv($handle, ['Регата', 'Тип', 'Место', 'Команда', 'Яхта', 'Очки'], ';');

                                foreach ($records as $result) {
                                    $result->load('items.team', 'items.yacht', 'regatta');
                                    $typeName = match ($result->result_type) {
                                        'preliminary' => 'Предварительный',
                                        'final'       => 'Финальный',
                                        default       => $result->result_type,
                                    };

                                    foreach ($result->items as $item) {
                                        fputcsv($handle, [
                                            $result->regatta?->name ?? '',
                                            $typeName,
                                            $item->final_position ?? '',
                                            $item->team?->name ?? '',
                                            $item->yacht?->name ?? '',
                                            $item->total_points,
                                        ], ';');
                                    }
                                }

                                fclose($handle);
                            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattaResults::route('/'),
        ];
    }
}
