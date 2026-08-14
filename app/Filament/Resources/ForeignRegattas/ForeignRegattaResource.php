<?php

declare(strict_types=1);

namespace App\Filament\Resources\ForeignRegattas;

use App\Enums\CharterPriceUnit;
use App\Enums\DownwindSail;
use App\Enums\FleetDivisionType;
use App\Enums\ParticipationOption;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\ForeignRegattas\Pages\ManageForeignRegattas;
use App\Filament\Resources\ForeignRegattaYachts\ForeignRegattaYachtResource;
use App\Models\ForeignRegatta;
use App\Models\Season;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Регаты подраздела «Регаты за рубежом» (раздел «Услуги»).
 *
 * Вводный текст страницы и общая галерея правятся в ServicesPageSettings —
 * здесь только сами регаты и их чартерный флот.
 */
class ForeignRegattaResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = ForeignRegatta::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Услуги: Регаты за рубежом';

    protected static ?int $navigationSort = 31;

    protected static string|\UnitEnum|null $navigationGroup = 'Услуги';

    /** Форматы, которые принимает загрузчик фотографий (HEIC нормализуется в JPEG на лету). */
    private const IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
        'image/heic',
        'image/heif',
    ];

    public static function getModelLabel(): string
    {
        return 'Регата за рубежом';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Регаты за рубежом';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('title')
                            ->label('Название регаты')
                            ->placeholder('Например: Croatia Sailing Week')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', $state ? Str::slug($state) : '')),

                        TextInput::make('slug')
                            ->label('Slug (адрес страницы)')
                            ->placeholder('avtomaticheski-zapolnyaetsya')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Textarea::make('summary')
                            ->label('Краткое описание')
                            ->helperText('Показывается в карточке регаты на витрине.')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Toggle::make('is_published')
                            ->label('Опубликована')
                            ->helperText('Неопубликованные регаты не видны ни на витрине, ни в календаре сезона.')
                            ->default(false),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Section::make('Даты и место')
                    ->description('Регата попадает в общий календарь регат сезона — по выбранному сезону, а если он не задан, по году начала.')
                    ->schema([
                        DatePicker::make('date_start')
                            ->label('Дата начала')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y'),

                        DatePicker::make('date_end')
                            ->label('Дата окончания')
                            ->helperText('Пусто — однодневная гонка.')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->afterOrEqual('date_start'),

                        Select::make('season_id')
                            ->label('Сезон')
                            ->helperText('Пусто — подставится сезон по году начала регаты.')
                            ->options(fn () => Season::query()
                                ->orderByDesc('year')
                                ->pluck('year', 'id')
                                ->all())
                            ->nullable(),

                        TextInput::make('country')
                            ->label('Страна')
                            ->placeholder('Например: Хорватия')
                            ->maxLength(255),

                        TextInput::make('region')
                            ->label('Регион или акватория')
                            ->placeholder('Например: Далмация')
                            ->maxLength(255),

                        TextInput::make('route_summary')
                            ->label('Маршрут одной строкой')
                            ->placeholder('Сплит — Хвар — Вис — Сплит')
                            ->helperText('Показывается в карточке регаты.')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Участие и стоимость')
                    ->schema([
                        CheckboxList::make('participation_options')
                            ->label('Варианты участия')
                            ->helperText('Из отмеченных вариантов заказчик выбирает в заявке. Флот добавляет варианты сам: лодка со свободными местами объявляет «Место», свободная лодка без шкипера — «Яхта целиком».')
                            ->options(ParticipationOption::options())
                            ->columns(3)
                            ->columnSpanFull(),

                        TextInput::make('price_per_seat')
                            ->label('Цена места в двухместной каюте')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽'),

                        TextInput::make('price_per_cabin')
                            ->label('Цена двухместной каюты')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽'),

                        Textarea::make('fleet_note')
                            ->label('Флот (текстом)')
                            ->placeholder('Например: монотип First 40.7, 12 лодок')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Textarea::make('price_note')
                            ->label('Примечание к стоимости')
                            ->placeholder('Например: перелёт, трансфер и судовая касса оплачиваются отдельно')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Флот регаты')
                    ->description('Флот объявляется дивизионами. «Флот одинаковых яхт» — характеристики задаются здесь один раз, лодки создаются автоматически по количеству. «Список конкретных яхт» — дивизион только группирует, характеристики вводятся у каждой лодки. Шкиперов, свободные места и занятость по каждой лодке правьте в разделе «Услуги: Флот регат».')
                    ->schema([
                        Repeater::make('divisions')
                            ->label('Дивизионы')
                            ->relationship()
                            ->addActionLabel('Добавить дивизион')
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->collapsed()
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => self::divisionItemLabel($state))
                            ->schema(self::divisionFields())
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Описание и расписание')
                    ->description('Текст с картинками в теле. Картинки добавляйте кнопкой «Прикрепить файлы»: при копировании из Word переносится только текст.')
                    ->schema([
                        RichEditor::make('content')
                            ->label('Описание регаты')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('services/foreign-regattas')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsMaxSize(5120)
                            ->columnSpanFull(),

                        RichEditor::make('schedule')
                            ->label('Маршрут и расписание')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('services/foreign-regattas')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsMaxSize(5120)
                            ->columnSpanFull(),
                    ]),

                Section::make('Обложка и фотографии')
                    ->description('Загрузите фото, затем задайте подписи ниже. Подписи выводятся под каждым снимком.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Обложка')
                            ->collection('cover')
                            ->image()
                            ->acceptedFileTypes(self::IMAGE_MIMES)
                            ->imageEditor()
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull(),

                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label('Фотографии')
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->acceptedFileTypes(self::IMAGE_MIMES)
                            ->imageEditor()
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->panelLayout('grid')
                            ->columnSpanFull(),

                        // Подпись храним в поле `name` медиа: это «человеческое» имя
                        // файла, отдельное от file_name, поэтому отдельная таблица
                        // подписей не нужна.
                        //
                        // Намеренно НЕ ->relationship(): репитер на связи при
                        // сохранении удаляет записи, которых нет в его состоянии
                        // (Repeater::saveToRelationship), а фотографии, загруженные
                        // загрузчиком выше в этот же submit, в состояние не
                        // попадают — их бы стёрло. Поэтому гидрируем и сохраняем
                        // вручную, обновляя только name и никогда ничего не удаляя.
                        Repeater::make('photo_captions')
                            ->label('Подписи к фотографиям')
                            ->helperText('Только что загруженные фотографии появятся в списке после сохранения.')
                            ->schema([
                                Hidden::make('media_id'),
                                TextInput::make('caption')
                                    ->label('Подпись')
                                    ->maxLength(255),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->dehydrated(false)
                            ->itemLabel(fn (array $state): ?string => $state['file_name'] ?? null)
                            ->afterStateHydrated(function (Repeater $component, ?Model $record): void {
                                $component->state(
                                    $record instanceof ForeignRegatta
                                        ? $record->getMedia('gallery')
                                            ->map(fn (Media $media): array => [
                                                'media_id' => (string) $media->getKey(),
                                                'file_name' => $media->file_name,
                                                'caption' => $media->name,
                                            ])
                                            ->values()
                                            ->all()
                                        : []
                                );
                            })
                            ->saveRelationshipsUsing(function (Repeater $component, ?Model $record): void {
                                if (! $record instanceof ForeignRegatta) {
                                    return;
                                }

                                foreach ((array) $component->getState() as $item) {
                                    if (empty($item['media_id'])) {
                                        continue;
                                    }

                                    // Обновляем адресно по id — снимки, загруженные
                                    // в этом же submit, репитер не видит и не трогает.
                                    $record->media()
                                        ->whereKey($item['media_id'])
                                        ->update(['name' => (string) ($item['caption'] ?? '')]);
                                }
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Видео')
                    ->description('Ссылки на YouTube, Rutube, VK Видео или Vimeo — на сайте отображаются плеером.')
                    ->schema([
                        Repeater::make('video_links')
                            ->label('Видео')
                            ->addActionLabel('Добавить видео')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => $state['caption'] ?? $state['url'] ?? null)
                            ->schema([
                                TextInput::make('url')
                                    ->label('Ссылка на видео')
                                    ->url()
                                    ->required()
                                    ->maxLength(2048),
                                TextInput::make('caption')
                                    ->label('Подпись')
                                    ->maxLength(255),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Поля дивизиона.
     *
     * Спецификация показывается только у дивизиона-флота: у списка конкретных
     * лодок она живёт на самих лодках, и пустые поля здесь только путали бы.
     *
     * @return list<Component>
     */
    private static function divisionFields(): array
    {
        $isFleet = fn (Get $get): bool => $get('type') === FleetDivisionType::Fleet->value;

        return [
            Select::make('type')
                ->label('Тип дивизиона')
                ->options(FleetDivisionType::options())
                ->default(FleetDivisionType::Fleet->value)
                ->helperText(fn (?string $state): string => FleetDivisionType::tryFrom((string) $state)?->hint() ?? '')
                ->required()
                ->live(),

            TextInput::make('name')
                ->label('Название дивизиона')
                ->placeholder('Например: Дивизион А')
                ->helperText('Необязательно — станет заголовком группы яхт на странице регаты.')
                ->maxLength(255),

            TextInput::make('yachts_count')
                ->label('Количество яхт в дивизионе')
                ->helperText('Столько карточек яхт появится в разделе «Услуги: Флот регат» — там укажете шкиперов и свободные места.')
                ->numeric()
                ->minValue(1)
                ->maxValue(200)
                ->required($isFleet)
                ->visible($isFleet),

            TextInput::make('model')
                ->label('Модель лодки')
                ->placeholder('Bavaria 46')
                ->required($isFleet)
                ->visible($isFleet)
                ->maxLength(255),

            TextInput::make('cabins')
                ->label('Кают')
                ->numeric()
                ->minValue(1)
                ->maxValue(20)
                ->required($isFleet)
                ->visible($isFleet),

            TextInput::make('year')
                ->label('Год выпуска')
                ->numeric()
                ->minValue(1900)
                ->maxValue((int) now()->addYear()->format('Y'))
                ->visible($isFleet),

            Select::make('downwind_sail')
                ->label('Спинакер / геннакер')
                ->options(DownwindSail::options())
                ->visible($isFleet),

            TextInput::make('price')
                ->label('Стоимость')
                ->numeric()
                ->minValue(0)
                ->suffix('₽')
                ->required($isFleet)
                ->visible($isFleet),

            Select::make('price_unit')
                ->label('За что цена')
                ->options(CharterPriceUnit::options())
                ->default(CharterPriceUnit::Regatta->value)
                ->visible($isFleet),

            TextInput::make('charter_fee')
                ->label('Сборы чартерной компании')
                ->numeric()
                ->minValue(0)
                ->suffix('₽')
                ->visible($isFleet),

            TextInput::make('deposit')
                ->label('Депозит')
                ->numeric()
                ->minValue(0)
                ->suffix('₽')
                ->visible($isFleet),

            TextInput::make('price_note')
                ->label('Примечание к стоимости')
                ->placeholder('Например: судовая касса оплачивается на месте')
                ->maxLength(255)
                ->visible($isFleet)
                ->columnSpan(2),

            Textarea::make('description')
                ->label('Описание лодки')
                ->rows(3)
                ->maxLength(2000)
                ->visible($isFleet)
                ->columnSpanFull(),

            SpatieMediaLibraryFileUpload::make('gallery')
                ->label('Фотографии лодки')
                ->helperText('Общая галерея для всех лодок дивизиона.')
                ->collection('gallery')
                ->multiple()
                ->reorderable()
                ->image()
                ->acceptedFileTypes(self::IMAGE_MIMES)
                ->imageEditor()
                ->disk('public')
                ->visibility('public')
                ->maxSize(10240)
                ->panelLayout('grid')
                ->visible($isFleet)
                ->columnSpanFull(),
        ];
    }

    /** @param  array<string, mixed>  $state */
    private static function divisionItemLabel(array $state): ?string
    {
        $name = trim((string) ($state['name'] ?? ''));
        $model = trim((string) ($state['model'] ?? ''));
        $count = (int) ($state['yachts_count'] ?? 0);

        $isFleet = ($state['type'] ?? null) === FleetDivisionType::Fleet->value;

        $spec = $isFleet
            ? trim($model.($count > 0 ? ' × '.$count : ''))
            : 'список яхт';

        return match (true) {
            $name !== '' && $spec !== '' => $name.' — '.$spec,
            $name !== '' => $name,
            $spec !== '' => $spec,
            default => null,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label('Обложка')
                    ->collection('cover'),
                TextColumn::make('title')
                    ->label('Регата')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('date_start')
                    ->label('Даты')
                    ->state(fn (ForeignRegatta $record): string => $record->dateRange())
                    ->sortable(),
                TextColumn::make('country')
                    ->label('Место')
                    ->state(fn (ForeignRegatta $record): ?string => $record->placeLabel())
                    ->placeholder('—'),
                TextColumn::make('season.year')
                    ->label('Сезон')
                    ->placeholder('—'),
                TextColumn::make('charter_yachts_count')
                    ->counts('charterYachts')
                    ->label('Яхт во флоте'),
                TextColumn::make('price_per_seat')
                    ->label('Цена места')
                    ->state(fn (ForeignRegatta $record): ?string => $record->seatPriceLabel())
                    ->placeholder('—'),
                TextColumn::make('service_requests_count')
                    ->counts('serviceRequests')
                    ->label('Заявок'),
                IconColumn::make('is_published')
                    ->label('Опубликована')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Опубликована'),
                Filter::make('upcoming')
                    ->label('Только предстоящие')
                    ->query(fn (Builder $query): Builder => $query->upcoming()),
                Filter::make('past')
                    ->label('Только прошедшие')
                    ->query(fn (Builder $query): Builder => $query->past()),
            ])
            ->emptyStateHeading('Зарубежных регат пока нет')
            ->emptyStateDescription('Добавьте регату — она появится на странице «Регаты за рубежом» и в календаре сезона.')
            ->recordActions([
                EditAction::make(),
                // Лодки правятся отдельным ресурсом: у каждой своя галерея,
                // шкипер и места, и их бывает несколько десятков.
                Action::make('fleet')
                    ->label('Флот')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->url(fn (ForeignRegatta $record): string => ForeignRegattaYachtResource::getUrl(parameters: [
                        'tableFilters' => ['foreign_regatta_id' => ['value' => $record->getKey()]],
                    ])),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Регата удалена'),
                    ),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['season', 'media']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageForeignRegattas::route('/'),
        ];
    }
}
