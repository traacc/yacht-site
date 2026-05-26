<?php

declare(strict_types=1);

namespace App\Filament\Resources\Regattas;

use App\Filament\Resources\Regattas\Pages\ManageRegattas;
use App\Models\Regatta;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Kpebedko22\FilamentYandexMap\Forms\Components\YandexMap;
use Kpebedko22\FilamentYandexMap\Enums\YandexMapMode;
use Kpebedko22\FilamentYandexMap\DTOs\Buttons\{ButtonData, ButtonOptions};
use Kpebedko22\FilamentYandexMap\Enums\Buttons\{ButtonFloat, ButtonSize};

class RegattaResource extends Resource
{
    protected static ?string $model = Regatta::class;

    protected static string|BackedEnum|null $navigationIcon = 'regatta';

    public static function getModelLabel(): string
    {
        return 'Регата';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Регаты';
    }

    public static function form(Schema $schema): Schema
    {
        $maxFiles      = (int) config('documents.max_files_per_type', 10);
        $acceptedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        return $schema
            ->components([
                Textarea::make('name')
                    ->label('Название')
                    ->placeholder('Введите название регаты')
                    ->required(),
                Select::make('season_id')
                    ->label('Сезон')
                    ->relationship('season', 'year')
                    ->required(),
                TextInput::make('level_coefficient')
                    ->label('Коэффициент соревнований')
                    ->placeholder('Введите коэффициент соревнований')
                    ->required()
                    ->numeric()
                    ->default(1.0)
                    ->columnSpanFull(),
                DatePicker::make('date_start')
                    ->label('Дата начала')
                    ->displayFormat('d.m.Y')
                    ->required(),
                DatePicker::make('date_end')
                    ->label('Дата окончания')
                    ->displayFormat('d.m.Y')
                    ->required(),

                TextInput::make('race_days_count')
                    ->label('Количество гоночных дней')
                    ->placeholder('1')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('races_count')
                    ->label('Количество гонок')
                    ->placeholder('1')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('location')
                    ->label('Локацию')
                    ->placeholder('Выберите локацию'),
                YandexMap::make('coordinates')
                    ->label('Местоположение на Яндекс.Картах')
                    ->columnSpanFull()
                    ->mode(YandexMapMode::Placemark)
                    ->apiKey('ffd9d711-109d-415d-bf73-e1a935512160')
                    ->lang('ru_RU')
                    ->center([55.7558, 37.6173])
                    ->zoom(10)
                    ->height('450px')
                    ->formatStateUsing(function ($state) {
                        if (is_string($state) && str_contains($state, ',')) {
                            [$lat, $lng] = explode(',', $state, 2);
                            return [
                                'lat' => (float) trim($lat),
                                'lng' => (float) trim($lng),
                            ];
                        }
                        return $state;
                    })
                    ->drawBtnParameters(
                        new ButtonData('Поставить'),
                        new ButtonOptions(float: ButtonFloat::Right),
                    )
                    ->dehydrateStateUsing(function ($state) {
                        if (is_array($state) && isset($state['lat'], $state['lng'])) {
                            return "{$state['lat']},{$state['lng']}";
                        }
                        return $state;
                    }),
                TextInput::make('water_area')
                    ->label('Акватория')
                    ->placeholder('Введите акваторию'),
                TextInput::make('short_description')
                    ->label('Краткое описание')
                    ->placeholder('Краткое описание')
                    ->columnSpanFull(),
                RichEditor::make('description')
                    ->label('О регате')
                    ->placeholder('Описание о регате')
                    ->columnSpanFull(),
                FileUpload::make('background_image')
                    ->label('Загрузить обложку')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->disk('public')
                    ->directory('regattas/covers')
                    ->visibility('public'),

                Textarea::make('prizes')
                    ->label('Призы')
                    ->placeholder('Описание призового фонда')
                    ->columnSpanFull(),
                Select::make('regatta_status')
                    ->label('Статус')
                    ->options(\App\Enums\RegattaStatus::class)
                    ->default(\App\Enums\RegattaStatus::Upcoming)
                    ->required(),
                Textarea::make('regulations')
                    ->label('Регламент')
                    ->placeholder('Описание регламента')
                    ->columnSpanFull(),

                Repeater::make('regatta_events')
                    ->relationship('races')
                    ->label('Расписание регаты')
                    ->schema([
                        TextInput::make('name')
                            ->label('Событие')
                            ->required(),
                        DateTimePicker::make('event_datetime')
                            ->label('Время')
                            ->displayFormat('d.m.Y H:i')
                            ->format('Y-m-d H:i:s'),
                        TextInput::make('description')
                            ->label('Описание'),
                    ])
                    ->itemLabel(fn (array $state, int $index): ?string => (! empty($state['event_datetime']) && ! empty($state['name']))
                        ? ($index + 1) . ". {$state['event_datetime']} — {$state['name']}"
                        : ($index + 1) . '. Новое событие')
                    ->columns(4)
                    ->columnSpanFull()
                    ->addActionLabel('Добавить пункт расписания')
                    ->collapsible(),

                // ── Обязательные документы ──────────────────
                Repeater::make('required_documents')
                    ->label('​')
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->default(fn () => array_map(
                        fn (array $doc) => [
                            'doc_type' => $doc['doc_type'],
                            'title'    => $doc['title'],
                            'files'    => [],
                        ],
                        ManageRegattas::getRequiredDocuments(),
                    ))
                    ->columns(1)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->rules([
                        function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                $requiredDocs = ManageRegattas::getRequiredDocuments();
                                $missing = [];

                                foreach ($requiredDocs as $required) {
                                    $docType = $required['doc_type'];
                                    $item = collect((array) $value)->first(
                                        fn (array $doc): bool => ($doc['doc_type'] ?? '') === $docType
                                    );

                                    $files = array_filter((array) ($item['files'] ?? []));

                                    if ($item === null || $files === []) {
                                        $missing[] = $required['title'];
                                    }
                                }

                                if ($missing !== []) {
                                    $fail('Загрузите следующие обязательные документы: ' . implode(', ', $missing) . '.');
                                }
                            };
                        },
                    ])
                    ->schema([
                        Hidden::make('doc_type'),
                        Hidden::make('title'),
                        FileUpload::make('files')
                            ->label('Файлы')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->directory('documents')
                            ->disk('public')
                            ->acceptedFileTypes($acceptedTypes)
                            ->maxSize(20480)
                            ->maxFiles($maxFiles)
                            ->downloadable()
                            ->helperText('Можно загрузить до ' . $maxFiles . ' файлов'),
                    ]),

                // ── Дополнительные документы (произвольные) ──
                /*
                Repeater::make('extra_documents')
                    ->label('Дополнительные документы')
                    ->columnSpanFull()
                    ->addActionLabel('Добавить документ')
                    ->collapsible()
                    ->defaultItems(0)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->schema([
                        Select::make('doc_type')
                            ->label('Тип')
                            ->options(fn () => \App\Models\YachtDocumentType::options())
                            ->default('other')
                            ->required(),
                        TextInput::make('title')
                            ->label('Название')
                            ->placeholder('Название документа')
                            ->required(),
                        FileUpload::make('files')
                            ->label('Файлы')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->directory('documents')
                            ->disk('public')
                            ->acceptedFileTypes($acceptedTypes)
                            ->maxSize(20480)
                            ->maxFiles($maxFiles)
                            ->downloadable()
                            ->helperText('Можно загрузить до ' . $maxFiles . ' файлов'),
                    ]),
                */
                // ── Обязательные документы для подачи заявок ──
                Section::make('Документы для заявок')
                    ->description('Документы, которые участник обязан приложить при подаче заявки на эту регату. Если ничего не выбрано — применяются глобальные настройки обязательных документов заявок.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('entry_required_documents')
                            ->label('Обязательные документы')
                            ->options(fn () => \App\Models\YachtDocumentType::cachedConfigurable()
                                ->pluck('label', 'key')
                                ->all())
                            ->columns(2)
                            ->gridDirection('row')
                            ->helperText('Отметьте типы документов, обязательных для заявки на эту регату.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Регата')
                    ->searchable(),
                TextColumn::make('season.year')
                    ->label('Сезон')
                    ->searchable(),
                TextColumn::make('date')
                    ->label('Дата')
                    ->getStateUsing(fn (Regatta $regatta): string => $regatta->dateRange()),
                TextColumn::make('water_area')
                    ->label('Акватория'),
                TextColumn::make('regatta_status')
                    ->label('Статус')
                    ->badge(),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Записей пока нет')
            ->filters([
                Filter::make('created_at_day')
                    ->label('Дата')
                    ->schema([
                        DatePicker::make('date')
                            ->label('Выберите дату')
                            ->displayFormat('d.m.Y')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'],
                            fn (Builder $query, $date) => $query
                                ->whereDate('date_start', '<=', $date)
                                ->whereDate('date_end', '>=', $date),
                        );
                    }),
                SelectFilter::make('regatta_status')
                    ->label('Статус')
                    ->options(\App\Enums\RegattaStatus::class),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать регату')
                    ->mountUsing(function (Schema $form, Regatta $record): void {
                        $sync         = app(\App\Actions\Document\SyncDocumentFilesAction::class);
                        $requiredDocs = ManageRegattas::getRequiredDocuments();
                        $requiredDocTypes = array_column($requiredDocs, 'doc_type');

                        $data = $record->toArray();
                        $data['required_documents'] = $sync->load($record, $requiredDocs);
                        $data['extra_documents']    = $sync->loadExtra($record, $requiredDocTypes);

                        $form->fill($data);
                    })
                    ->using(function (Regatta $record, array $data): Regatta {
                        $requiredDocs = $data['required_documents'] ?? [];
                        $extraDocs    = $data['extra_documents'] ?? [];
                        unset($data['required_documents'], $data['extra_documents']);

                        $record->update($data);

                        $sync = app(\App\Actions\Document\SyncDocumentFilesAction::class);
                        $sync->execute($record, $requiredDocs);
                        $sync->execute($record, $extraDocs);

                        $requiredDocTypes = array_column(ManageRegattas::getRequiredDocuments(), 'doc_type');
                        $activeExtraTypes = array_filter(array_column($extraDocs, 'doc_type'));
                        $sync->pruneOrphanedDocTypes($record, $requiredDocTypes, $activeExtraTypes);

                        return $record;
                    }),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattas::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Определяет читаемую метку для документа в Repeater.
     */
    public static function resolveDocumentLabel(array $state): ?string
    {
        $docType = $state['doc_type'] ?? null;

        if ($docType === null) {
            return $state['title'] ?? null;
        }

        $model = \App\Models\YachtDocumentType::cachedAll()
            ->first(fn (\App\Models\YachtDocumentType $t) => $t->key === $docType);

        return $model?->label ?? ($state['title'] ?? null);
    }
}
