<?php

declare(strict_types=1);

namespace App\Filament\Resources\Yachts;

use App\Filament\Resources\Yachts\Pages\ManageYachts;
use App\Models\Yacht;
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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class YachtResource extends Resource
{
    protected static ?string $model = Yacht::class;

    protected static string|BackedEnum|null $navigationIcon = 'yacht';

    public static function getModelLabel(): string
    {
        return 'Яхта';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Яхты';
    }

    public static function form(Schema $schema): Schema
    {
        $maxFiles       = (int) config('documents.max_files_per_type', 10);
        $acceptedTypes  = [
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
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите название яхты')
                    ->required(),
                TextInput::make('gims_number')
                    ->label('Номер ГИМС')
                    ->placeholder('Введите номер ГИМС'),
                TextInput::make('vfps_number')
                    ->label('Номер на парусе')
                    ->placeholder('Введите номер на парусе')
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('user_id')
                    ->label('Пользователь')
                    ->relationship('user', 'name')
                    ->placeholder('Пользователь зарегистрировавший яхту'),

                TextInput::make('project')
                    ->label('Проект')
                    ->placeholder('Проект яхты')
                    ->default('Carter30'),
                TextInput::make('year')
                    ->label('Год выпуска')
                    ->placeholder('Год выпуска')
                    ->numeric(),
                TextInput::make('current_mass_kg')
                    ->label('Масса (кг)')
                    ->placeholder('Масса в кг')
                    ->numeric(),
                TextInput::make('class')
                    ->label('Класс')
                    ->placeholder('Класс яхты')
                    ->default('Carter30'),

                TextInput::make('reg_place')
                    ->label('Место регистрации')
                    ->placeholder('Место регистрации'),

                Select::make('approval_status')
                    ->label('Статус одобрения')
                    ->placeholder('Выберите статус')
                    ->options([
                        'pending'  => 'На рассмотрении',
                        'approved' => 'Одобрена',
                        'rejected' => 'Отклонена',
                    ])
                    ->default('pending')
                    ->required(),
                /*
                Repeater::make('past_regattas')
                    ->label('Прошедшие соревнования')
                    ->columnSpanFull()
                    ->addActionLabel('Добавить соревнования')
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Новое соревнование')
                    ->defaultItems(0)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название регаты')
                            ->placeholder('Введите название регаты')
                            ->required(),
                        DatePicker::make('date_start')
                            ->label('Дата начала')
                            ->minDate(now()->subYears(100)) 
                            ->maxDate(now()->addYears(100))
                            ->required(),
                        DatePicker::make('date_end')
                            ->label('Дата окончания')
                            ->minDate(now()->subYears(100)) 
                            ->maxDate(now()->addYears(100))
                            ->required(),
                    ])
                    ->columns(3),
                */
                SpatieMediaLibraryFileUpload::make('gallery')
                    ->label('Галерея')
                    ->collection('gallery')
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->imageEditor()
                    ->disk('public')
                    ->visibility('public')
                    ->columnSpanFull(),
                // ── Обязательные документы ──────────────────
                Repeater::make('required_documents')
                    ->label('Обязательные документы')
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
                        ManageYachts::getRequiredDocuments(),
                    ))
                    ->columns(1)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->rules([
                        function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                $requiredDocs = ManageYachts::getRequiredDocuments();
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
                Repeater::make('extra_documents')
                    ->label('Дополнительные документы')
                    ->columnSpanFull()
                    ->addActionLabel('Добавить документ')
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->schema([
                        Hidden::make('doc_type')
                            ->label('Тип')
                            //->options(fn () => \App\Models\YachtDocumentType::options())
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Яхта')
                    ->searchable()->sortable(),
                TextColumn::make('gims_number')
                    ->label('Номер ГИМС')
                    ->searchable()->sortable(),
                TextColumn::make('vfps_number')
                    ->label('Парус №')
                    ->searchable()->sortable(),
                TextColumn::make('user.name')
                    ->label('Владелец')
                    ->searchable()->sortable(['name']),
                TextColumn::make('orc_cert')
                    ->label('ORC-сертификат')
                    ->state(fn ($record) => $record->documents()
                        ->where('doc_type', 'orc_cert_type')
                        ->exists())
                    ->formatStateUsing(fn ($state) => $state ? 'Есть' : 'Нет')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),

                TextColumn::make('approval_status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'На рассмотрении',
                        'approved'  => 'Одобрена',
                        'rejected'  => 'Отклонена',
                        'withdrawn' => 'Отозвана',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'approved'  => 'success',
                        'rejected'  => 'danger',
                        'withdrawn' => 'gray',
                        default     => 'gray',
                    }),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Записей пока нет')
            ->filters([
                SelectFilter::make('approval_status')
                    ->label('Статус')
                    ->options([
                        'pending'   => 'На рассмотрении',
                        'approved'  => 'Одобрена',
                        'rejected'  => 'Отклонена',
                        'withdrawn' => 'Отозвана',
                    ]),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать яхту')
                    ->mountUsing(function (Schema $form, Yacht $record): void {
                        $sync = app(\App\Actions\Document\SyncDocumentFilesAction::class);
                        $requiredDocs = ManageYachts::getRequiredDocuments();
                        $requiredDocTypes = array_column($requiredDocs, 'doc_type');

                        $data = $record->toArray();
                        $data['required_documents'] = $sync->load($record, $requiredDocs);
                        $data['extra_documents']    = $sync->loadExtra($record, $requiredDocTypes);

                        $form->fill($data);
                    })
                    ->using(function (Yacht $record, array $data): Yacht {
                        $requiredDocs = $data['required_documents'] ?? [];
                        $extraDocs    = $data['extra_documents'] ?? [];
                        unset($data['required_documents'], $data['extra_documents']);

                        $record->update($data);

                        $sync = app(\App\Actions\Document\SyncDocumentFilesAction::class);
                        $sync->execute($record, $requiredDocs);
                        $sync->execute($record, $extraDocs);

                        // Удалить осиротевшие типы, которых больше нет в extra
                        $requiredDocTypes = array_column(ManageYachts::getRequiredDocuments(), 'doc_type');
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
            'index' => ManageYachts::route('/'),
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
