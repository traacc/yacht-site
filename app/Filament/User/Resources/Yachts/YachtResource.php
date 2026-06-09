<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Yachts;

use App\Filament\User\Resources\Yachts\Pages\ManageYachts;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class YachtResource extends Resource
{
    protected static ?string $model = Yacht::class;

    protected static string|BackedEnum|null $navigationIcon = 'yacht';

    public static function getModelLabel(): string
    {
        return 'Моя Яхта';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Мои Яхты';
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
                Placeholder::make('note_form')
                ->hiddenLabel()
                ->content(new HtmlString('Выберите яхту из базы Ассоциации или заполните данные вручную. Номер ВФПС будет использован как уникальный ID яхты в системе.'))
                ->columnSpanFull(),
                Hidden::make('selected_yacht_id'),
                Select::make('yacht_search')->placeholder('Номер ВФПС или название яхты')->columnSpanFull()->label('Найти яхту в базе')->searchable()
                ->getSearchResultsUsing(fn (string $search): array => \App\Models\Yacht::query()
                    ->withoutGlobalScope(\App\Models\Scopes\OwnedScope::class)
                    ->whereNull('user_id')
                    ->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('vfps_number', 'like', "%{$search}%");
                    })
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn ($yacht) => [$yacht->id => trim(($yacht->name ?? '') . ($yacht->vfps_number ? " ({$yacht->vfps_number})" : ''))])
                    ->toArray())
                ->getOptionLabelUsing(function ($value): ?string {
                    $yacht = \App\Models\Yacht::query()
                        ->withoutGlobalScope(\App\Models\Scopes\OwnedScope::class)
                        ->find($value);
                    return $yacht ? trim(($yacht->name ?? '') . ($yacht->vfps_number ? " ({$yacht->vfps_number})" : '')) : null;
                })
                ->live()
                ->afterStateUpdated(function ($state, $set) {
                    $yacht = \App\Models\Yacht::query()
                        ->withoutGlobalScope(\App\Models\Scopes\OwnedScope::class)
                        ->find($state);
                    if ($yacht) {
                        $set('selected_yacht_id', $yacht->id);
                        $set('name', $yacht->name);
                        $set('vfps_number', $yacht->vfps_number);
                        $set('gims_number', $yacht->gims_number);
                        $set('class', $yacht->class);
                        $set('project', $yacht->project);
                        $set('year', $yacht->year);
                        $set('reg_place', $yacht->reg_place);
                        $set('current_mass_kg', $yacht->current_mass_kg);
                    }
                }),
                TextInput::make('name')
                    ->required()->label('Название яхты')->placeholder('Введите название яхты')
                    ->rules([
                        fn (callable $get, $record) => \Illuminate\Validation\Rule::unique('yachts', 'name')->ignore($record?->id ?? $get('selected_yacht_id')),
                    ])
                    ->validationMessages([
                        'unique' => 'Яхта с таким именем уже существует в системе.',
                    ])
                    ->disabled(fn (callable $get) => filled($get('selected_yacht_id'))),
                TextInput::make('gims_number')->label('Номер ГИМС')->placeholder('Введите номер ГИМС'),
                TextInput::make('vfps_number')
                    ->required()
                    ->rules([
                        fn (callable $get, $record) => \Illuminate\Validation\Rule::unique('yachts', 'vfps_number')->ignore($record?->id ?? $get('selected_yacht_id')),
                    ])
                ->validationMessages([
                    'unique' => 'Яхта с таким номером ВФПС уже существует в системе.',
                ])->label('Номер паруса')->placeholder('Введите номер паруса (ВФПС)')
                    ->disabled(fn (callable $get) => filled($get('selected_yacht_id'))),
                TextInput::make('class')->label('Класс')->placeholder('Введите класс яхты')->default('Carter30'),

                Placeholder::make('Параметры')->columnSpanFull(),
                TextInput::make('project')->label('Проект')->placeholder('Введите проект яхты')->default('Carter30'),
                TextInput::make('year')->label('Год выпуска')->placeholder('Введите год выпуска')
                    ->numeric(),
                TextInput::make('current_mass_kg')->label('Масса яхты')->placeholder('Введите массу яхты')->numeric(),
                TextInput::make('reg_place')->label('Место регистрации')->placeholder('Введите место регистрации'),

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
                /*
                Repeater::make('required_documents')
                    ->label('Документы')
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
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(20480)
                            ->maxFiles(config('documents.max_files_per_type', 10))
                            ->downloadable()
                            ->helperText(fn () => 'Можно загрузить до ' . config('documents.max_files_per_type', 10) . ' файлов'),
                    ]),
                */
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
                            ->minDate(now()->subYears(100)) 
                            ->maxDate(now()->addYears(100))
                            ->label('Дата начала')
                            ->required(),
                        DatePicker::make('date_end')
                            ->minDate(now()->subYears(100)) 
                            ->maxDate(now()->addYears(100))
                            ->label('Дата окончания')
                            ->required(),
                    ])
                    ->columns(3),
                */
                // ── Дополнительные документы (произвольные) ──
                Repeater::make('extra_documents')
                    ->label('Документы')
                    ->columnSpanFull()
                    ->addActionLabel('Добавить документ')
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->defaultItems(0)
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()->label('Название'),
                TextColumn::make('vfps_number')
                    ->searchable()->label('#'),


                TextColumn::make('approval_status')
                    ->badge()->label('Статус')->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'На проверке',
                    'approved' => 'Активная',
                    'rejected' => 'Отклонена',
                    default => $state,
                })->color(fn (string $state): string => match ($state) {
                    'pending' => 'gray',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
            ])
            ->filters([
                //TrashedFilter::make(),
            ])->emptyStateHeading('Записей пока нет')
            ->recordActions([
                EditAction::make()->hiddenLabel()->modalHeading('Редактировать яхту')
                    ->mountUsing(function (\Filament\Schemas\Schema $form, Yacht $record): void {
                        $data = $record->toArray();
                        $data['required_documents'] = app(\App\Actions\Document\SyncDocumentFilesAction::class)
                            ->load($record, ManageYachts::getRequiredDocuments());
                        $form->fill($data);
                    })
                    ->using(function (Yacht $record, array $data): Yacht {
                        $docs = $data['required_documents'] ?? [];
                        unset($data['required_documents'], $data['yacht_search']);

                        $record->update($data);

                        app(\App\Actions\Document\SyncDocumentFilesAction::class)
                            ->execute($record, $docs);

                        return $record;
                    }),
                DeleteAction::make()->hiddenLabel(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])->stackedOnMobile();
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

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
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
