<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Yachts;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\Yacht\SyncYachtOptionsAction;
use App\Filament\Forms\Components\RentalCalendar;
use App\Filament\User\Resources\OwnershipTransfers\OwnershipTransferResource;
use App\Filament\User\Resources\Yachts\Pages\ManageYachts;
use App\Models\Scopes\OwnedScope;
use App\Models\Yacht;
use App\Models\YachtDocumentType;
use App\Models\YachtRental;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

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
        $maxFiles = (int) config('documents.max_files_per_type', 10);
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
                Placeholder::make('note_form')
                    ->hiddenLabel()
                    ->content(new HtmlString('Выберите яхту из базы Ассоциации или заполните данные вручную. Номер ВФПС будет использован как уникальный ID яхты в системе.'))
                    ->columnSpanFull(),
                Hidden::make('selected_yacht_id'),
                Hidden::make('transfer_yacht_id'),
                Hidden::make('is_owned_yacht'),
                Select::make('yacht_search')->placeholder('Номер ВФПС или название яхты')->columnSpanFull()->label('Найти яхту в базе')->searchable()
                    ->options(fn (): array => static::searchableYachtsQuery()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($yacht) => [$yacht->id => static::yachtSearchLabel($yacht)])
                        ->toArray())
                    ->getSearchResultsUsing(fn (string $search): array => static::searchableYachtsQuery()
                        ->where(function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('vfps_number', 'like', "%{$search}%");
                        })
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn ($yacht) => [$yacht->id => static::yachtSearchLabel($yacht)])
                        ->toArray())
                    ->getOptionLabelUsing(function ($value): ?string {
                        $yacht = Yacht::query()
                            ->withoutGlobalScope(OwnedScope::class)
                            ->find($value);

                        return $yacht ? static::yachtSearchLabel($yacht) : null;
                    })
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        $yacht = Yacht::query()
                            ->withoutGlobalScope(OwnedScope::class)
                            ->find($state);

                        if (! $yacht) {
                            return;
                        }

                        // Яхта уже принадлежит другому участнику — регистрация невозможна,
                        // предлагаем оформить запрос на передачу владения.
                        $isOwnedByOther = $yacht->user_id !== null && $yacht->user_id !== auth()->id();

                        $set('is_owned_yacht', $isOwnedByOther);
                        $set('transfer_yacht_id', $isOwnedByOther ? $yacht->id : null);

                        if ($isOwnedByOther) {
                            $set('selected_yacht_id', null);

                            return;
                        }

                        $set('selected_yacht_id', $yacht->id);
                        $set('name', $yacht->name);
                        $set('vfps_number', $yacht->vfps_number);
                        $set('gims_number', $yacht->gims_number);
                        $set('class', $yacht->class);
                        $set('project', $yacht->project);
                        $set('year', $yacht->year);
                        $set('reg_place', $yacht->reg_place);
                        $set('home_region', $yacht->home_region);
                        $set('mooring_place', $yacht->mooring_place);
                        $set('current_mass_kg', $yacht->current_mass_kg);
                    }),

                Placeholder::make('owned_yacht_notice')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => (bool) $get('is_owned_yacht'))
                    ->content(new HtmlString(
                        '<div class="text-warning-600">Эта яхта зарегистрирована другим участником. '
                        .'Если вы являетесь собствеником этой яхты, то отправьте запрос и приложите документ, '
                        .'подтверждающий ваше право собственности.</div>'
                    )),

                Actions::make([
                    static::requestTransferAction(),
                ])
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => (bool) $get('is_owned_yacht')),

                TextInput::make('name')
                    ->required()->label('Название яхты')->placeholder('Введите название яхты'),
                /*
                    ->rules([
                        fn (callable $get, $record) => \Illuminate\Validation\Rule::unique('yachts', 'name')->ignore($record?->id ?? $get('selected_yacht_id')),
                    ])
                    ->validationMessages([
                        'unique' => 'Яхта с таким именем уже существует в системе.',
                    ])
                    ->disabled(fn (callable $get) => filled($get('selected_yacht_id'))),
                    */
                TextInput::make('gims_number')->label('Номер ГИМС')->placeholder('Введите номер ГИМС')
                    ->hintIcon('heroicon-o-question-mark-circle', 'Номер яхты в реестре Государственной инспекции по маломерным судам (ГИМС).'),
                TextInput::make('vfps_number')
                    ->required()
                    ->rules([
                        // Блокируем только дубликаты яхт, уже принадлежащих кому-то.
                        // Свободные яхты (без user_id) будут перезаписаны (присвоены).
                        fn (callable $get, $record) => Rule::unique('yachts', 'vfps_number')
                            ->ignore($record?->id ?? $get('selected_yacht_id'))
                            ->whereNotNull('user_id'),
                    ])
                    ->validationMessages([
                        'unique' => 'Яхта с таким номером ВФПС уже существует в системе.',
                    ])->label('Номер паруса')->placeholder('Введите номер паруса (ВФПС)')
                    ->hintIcon('heroicon-o-question-mark-circle', 'ВФПС — Всероссийская федерация парусного спорта. Номер из её реестра становится уникальным ID яхты в системе Ассоциации.')
                    ->disabled(fn (callable $get) => filled($get('selected_yacht_id'))),
                TextInput::make('class')->label('Класс')->placeholder('Введите класс яхты')->default('Carter30'),

                Placeholder::make('Параметры')->columnSpanFull(),
                TextInput::make('project')->label('Проект')->placeholder('Введите проект яхты')->default('Carter30'),
                TextInput::make('year')->label('Год выпуска')->placeholder('Введите год выпуска')
                    ->numeric(),
                TextInput::make('current_mass_kg')->label('Масса яхты')->placeholder('Введите массу яхты')->numeric(),
                TextInput::make('reg_place')->label('Место регистрации')->placeholder('Введите место регистрации'),
                TextInput::make('home_region')->label('Регион базирования')->placeholder('Город или область'),
                TextInput::make('mooring_place')->label('Место стоянки')->placeholder('Название яхт-клуба'),
                TextInput::make('orc_cert_url')->label('ORC-сертификат')->placeholder('https://...')
                    ->url()->maxLength(255)
                    ->hintIcon('heroicon-o-question-mark-circle', 'Ссылка на действующий ORC-сертификат яхты.'),

                Placeholder::make('Опции')->columnSpanFull(),
                ...app(SyncYachtOptionsAction::class)->formComponents(),

                // ── Аренда яхты ──
                Placeholder::make('Аренда')->columnSpanFull(),
                Toggle::make('for_rent')
                    ->label('Сдаётся в аренду')
                    ->helperText('Включите, чтобы указать регаты и стоимость аренды яхты.')
                    ->live()
                    ->columnSpanFull(),
                RentalCalendar::make('rentals')
                    ->label('Периоды аренды')
                    ->helperText('Отметьте периоды аренды на календаре и укажите стоимость.')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => (bool) $get('for_rent')),

                SpatieMediaLibraryFileUpload::make('gallery')
                    ->label('Галерея')
                    ->collection('gallery')
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'])
                    ->imageEditor()
                    ->disk('public')
                    ->visibility('public')
                    ->maxSize(512)
                    ->panelLayout('grid')
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('interior_gallery')
                    ->label('Галерея интерьера')
                    ->collection('interior_gallery')
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'])
                    ->imageEditor()
                    ->disk('public')
                    ->visibility('public')
                    ->maxSize(512)
                    ->panelLayout('grid')
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
                Repeater::make('suitable_for')
                    ->label('Для чего подходит яхта')
                    ->columnSpanFull()
                    ->addActionLabel('Добавить значение')
                    ->reorderable()
                    ->defaultItems(0)
                    ->simple(
                        TextInput::make('value')
                            ->label('Значение')
                            ->placeholder('Например: соревнования')
                            ->required(),
                    ),
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
                            // ->options(fn () => \App\Models\YachtDocumentType::options())
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
                            ->helperText('Можно загрузить до '.$maxFiles.' файлов'),
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
                // TrashedFilter::make(),
            ])->emptyStateHeading('Записей пока нет')
            ->recordActions([
                EditAction::make()->hiddenLabel()->modalHeading('Редактировать яхту')
                    ->mountUsing(function (Schema $form, Yacht $record): void {
                        $data = $record->toArray();
                        $data['required_documents'] = app(SyncDocumentFilesAction::class)
                            ->load($record, ManageYachts::getRequiredDocuments());
                        $data += app(SyncYachtOptionsAction::class)->load($record);
                        $data['rentals'] = $record->rentals()
                            ->get()
                            ->map(fn (YachtRental $rental): array => [
                                'date_start' => $rental->date_start?->toDateString(),
                                'date_end' => $rental->date_end?->toDateString(),
                                'price_event' => $rental->price_event,
                                'price_pro' => $rental->price_pro,
                            ])
                            ->toArray();
                        $form->fill($data);
                    })
                    ->using(function (Yacht $record, array $data): Yacht {
                        $docs = $data['required_documents'] ?? [];
                        $rentals = $data['rentals'] ?? [];
                        $optionSync = app(SyncYachtOptionsAction::class);
                        $optionSelections = $optionSync->extract($data);
                        unset($data['required_documents'], $data['rentals'], $data['yacht_search']);

                        $record->update($data);

                        app(SyncDocumentFilesAction::class)
                            ->execute($record, $docs);

                        static::syncRentals($record, $rentals);
                        $optionSync->execute($record, $optionSelections);

                        return $record;
                    }),
                DeleteAction::make()->hiddenLabel()
                    ->modalHeading('Удалить яхту')
                    ->hidden(false) // показывать и для уже удалённых записей
                    ->using(fn (Yacht $record): bool => (bool) $record->forceDelete()),
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

        $model = YachtDocumentType::cachedAll()
            ->first(fn (YachtDocumentType $t) => $t->key === $docType);

        return $model?->label ?? ($state['title'] ?? null);
    }

    /**
     * Яхты, доступные для поиска при регистрации: свободные (без владельца)
     * и принадлежащие другим участникам. Свои яхты исключаются.
     */
    public static function searchableYachtsQuery(): Builder
    {
        return Yacht::query()
            ->withoutGlobalScope(OwnedScope::class)
            ->where(function (Builder $q): void {
                $q->whereNull('user_id')
                    ->orWhere('user_id', '!=', auth()->id());
            });
    }

    public static function yachtSearchLabel(Yacht $yacht): string
    {
        return trim(($yacht->name ?? '').($yacht->vfps_number ? " ({$yacht->vfps_number})" : ''));
    }

    /**
     * Синхронизирует периоды аренды яхты с переданными строками формы.
     * Полностью пересоздаёт записи, т.к. у периодов нет естественного ключа.
     *
     * @param  array<int, array{date_start?: string, date_end?: string, price_event?: mixed, price_pro?: mixed}>  $rentals
     */
    public static function syncRentals(Yacht $yacht, array $rentals): void
    {
        // Если яхта не сдаётся — очищаем все периоды аренды.
        if (! $yacht->for_rent) {
            $yacht->rentals()->delete();

            return;
        }

        $yacht->rentals()->delete();

        foreach ($rentals as $rental) {
            $start = $rental['date_start'] ?? null;
            $end = $rental['date_end'] ?? null;

            if (! $start || ! $end) {
                continue;
            }

            $yacht->rentals()->create([
                'date_start' => $start,
                'date_end' => $end,
                'price_event' => $rental['price_event'] ?? null,
                'price_pro' => $rental['price_pro'] ?? null,
            ]);
        }
    }

    /**
     * Быстрое действие «Запросить передачу яхты» для уже занятой яхты,
     * выбранной в поиске. Открывает форму заявки с предзаполненной яхтой.
     */
    public static function requestTransferAction(): Action
    {
        return Action::make('requestTransfer')
            ->label('Запросить передачу этой яхты')
            ->icon('heroicon-o-arrows-right-left')
            ->color('white')
            ->modalHeading('Запросить передачу яхты')
            ->modalSubmitActionLabel('Отправить заявку')
            ->schema(OwnershipTransferResource::formComponents())
            ->fillForm(fn (Get $get): array => [
                'yacht_id' => $get('transfer_yacht_id'),
            ])
            ->action(function (array $data): void {
                OwnershipTransferResource::createTransfer($data);

                Notification::make()
                    ->success()
                    ->title('Заявка отправлена')
                    ->body('Администратор рассмотрит вашу заявку на передачу яхты.')
                    ->send();
            });
    }
}
