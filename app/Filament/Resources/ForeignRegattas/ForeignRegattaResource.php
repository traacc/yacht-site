<?php

declare(strict_types=1);

namespace App\Filament\Resources\ForeignRegattas;

use App\Enums\CharterPriceUnit;
use App\Enums\CharterYachtStatus;
use App\Enums\Currency;
use App\Enums\DownwindSail;
use App\Enums\FleetDivisionType;
use App\Enums\ParticipationOption;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\CharterYachtModels\CharterYachtModelResource;
use App\Filament\Resources\ForeignRegattas\Pages\ManageForeignRegattas;
use App\Filament\Resources\ForeignRegattaYachts\ForeignRegattaYachtResource;
use App\Models\CharterYachtModel;
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

                        Select::make('currency')
                            ->label('Валюта')
                            ->helperText('Одна на все цены регаты: и в дивизионах, и у каждой лодки.')
                            ->options(Currency::options())
                            ->default(Currency::default()->value)
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('price_per_seat')
                            ->label('Цена места в двухместной каюте')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

                        TextInput::make('price_per_cabin')
                            ->label('Цена двухместной каюты')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

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
                    ->description('Флот объявляется дивизионами. «Флот одинаковых яхт» — характеристики и цены (место, каюта, яхта целиком) задаются здесь один раз, лодки создаются автоматически по количеству. «Список конкретных яхт» — здесь только единое описание и галерея дивизиона, а характеристики и цены вводятся у каждой лодки. Шкиперов, свободные места и занятость по каждой лодке правьте в разделе «Услуги: Флот регат».')
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
     * Знак валюты для полей суммы по состоянию формы.
     *
     * Запись в этот момент ещё не сохранена, поэтому валюту регаты берём из
     * состояния формы; путь до неё зависит от вложенности репитера.
     */
    private static function currencySymbol(Get $get, string ...$paths): string
    {
        foreach ($paths === [] ? ['currency'] : $paths as $path) {
            $state = $get($path);

            if ($state instanceof Currency || (is_string($state) && $state !== '')) {
                return Currency::fromNullable($state)->symbol();
            }
        }

        return Currency::default()->symbol();
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
        $type = fn (Get $get): ?FleetDivisionType => FleetDivisionType::tryFrom((string) $get('type'));

        // Монотип без выбора яхт: одна модель на весь дивизион, вводится руками.
        $isMonotype = fn (Get $get): bool => $type($get)?->sharesSpec() ?? false;
        // Оба монотипа: цена общая и задаётся здесь.
        $sharesPrices = fn (Get $get): bool => $type($get)?->sharesPrices() ?? false;
        // Монотип с выбором и гандикап: лодки добавляются поимённо.
        $picksYachts = fn (Get $get): bool => $type($get)?->picksYachts() ?? false;

        // Знак валюты внутри репитера: она одна на регату и лежит уровнем
        // выше (`../../`).
        $divisionCurrency = fn (Get $get): string => self::currencySymbol($get, '../../currency');

        return [
            Select::make('type')
                ->label('Тип дивизиона')
                ->options(FleetDivisionType::options())
                ->default(FleetDivisionType::Monotype->value)
                ->helperText(fn (?string $state): string => FleetDivisionType::tryFrom((string) $state)?->hint() ?? '')
                ->required()
                ->live(),

            TextInput::make('name')
                ->label('Название дивизиона')
                ->placeholder('Например: Дивизион А')
                ->helperText('Необязательно — станет заголовком группы яхт на странице регаты.')
                ->maxLength(255),

            TextInput::make('yachts_taken')
                ->label('Яхт целиком — занято')
                ->helperText('Из указанного количества. Столько лодок уже забрали целиком.')
                ->numeric()
                ->minValue(0)
                ->maxValue(200)
                ->default(0)
                ->visible($isMonotype),

            TextInput::make('yachts_count')
                ->label('Количество яхт в дивизионе')
                ->helperText('Столько карточек яхт появится в разделе «Услуги: Флот регат» — там укажете названия, годы, шкиперов и свободные места.')
                ->numeric()
                ->minValue(1)
                ->maxValue(200)
                ->required($isMonotype)
                ->visible($isMonotype),

            // Монотип без выбора: модель одна на весь дивизион и вводится
            // строкой — справочник здесь не участвует.
            TextInput::make('model')
                ->label('Модель лодки')
                ->placeholder('Bavaria 46')
                ->required($isMonotype)
                ->visible($isMonotype)
                ->maxLength(255),

            TextInput::make('cabins')
                ->label('Кают')
                ->numeric()
                ->minValue(1)
                ->maxValue(20)
                ->visible($isMonotype),

            TextInput::make('year')
                ->label('Год выпуска')
                ->numeric()
                ->minValue(1900)
                ->maxValue((int) now()->addYear()->format('Y'))
                ->visible($isMonotype),

            Select::make('downwind_sail')
                ->label('Спинакер / геннакер')
                ->options(DownwindSail::options())
                ->visible($isMonotype),

            TextInput::make('base_marina')
                ->label('База (марина)')
                ->placeholder('Marina Kaštela')
                ->maxLength(255)
                ->visible($isMonotype),

            TextInput::make('price')
                ->label('Стоимость яхты целиком')
                ->helperText('Пусто — лодки дивизиона целиком не сдаются, продаются только места и каюты.')
                ->numeric()
                ->minValue(0)
                ->suffix($divisionCurrency)
                ->visible($sharesPrices),

            Select::make('price_unit')
                ->label('За что цена')
                ->options(CharterPriceUnit::options())
                ->default(CharterPriceUnit::Regatta->value)
                ->visible($sharesPrices),

            TextInput::make('seat_price')
                ->label('Стоимость места в каюте')
                ->helperText('Кнопка «Место» появится у тех лодок дивизиона, где указаны свободные места.')
                ->numeric()
                ->minValue(0)
                ->suffix($divisionCurrency)
                ->visible($sharesPrices),

            TextInput::make('solo_seat_price')
                ->label('Стоимость места в одноместной каюте')
                ->helperText('Пусто — такие места не продаются.')
                ->numeric()
                ->minValue(0)
                ->suffix($divisionCurrency)
                ->visible($sharesPrices),

            TextInput::make('cabin_price')
                ->label('Стоимость двухместной каюты')
                ->helperText('Пусто — каюты по этому дивизиону не продаются.')
                ->numeric()
                ->minValue(0)
                ->suffix($divisionCurrency)
                ->visible($sharesPrices),

            TextInput::make('charter_fee')
                ->label('Сборы чартерной компании')
                ->numeric()
                ->minValue(0)
                ->suffix($divisionCurrency)
                ->visible($sharesPrices),

            TextInput::make('deposit')
                ->label('Депозит')
                ->numeric()
                ->minValue(0)
                ->suffix($divisionCurrency)
                ->visible($sharesPrices),

            TextInput::make('downwind_sail_price')
                ->label('Аренда спинакера / геннакера')
                ->numeric()
                ->minValue(0)
                ->suffix($divisionCurrency)
                ->visible($sharesPrices),

            TextInput::make('downwind_sail_deposit')
                ->label('Депозит за спинакер / геннакер')
                ->numeric()
                ->minValue(0)
                ->suffix($divisionCurrency)
                ->visible($sharesPrices),

            TextInput::make('price_note')
                ->label('Примечание к стоимости')
                ->placeholder('Например: судовая касса оплачивается на месте')
                ->maxLength(255)
                ->visible($sharesPrices)
                ->columnSpan(2),

            // Занятость по типам мест. Только у монотипа без выбора яхт: там
            // лодки взаимозаменяемы и места продаются общим пулом, поэтому и
            // счётчик один на дивизион (@see App\Models\ForeignRegattaDivision::sellsDirectly()).
            ...self::occupancyFields($isMonotype),

            // Описание и галерея есть у всех типов: у монотипа без выбора яхт
            // они наследуются карточками лодок, у остальных — это единое
            // описание дивизиона над списком яхт.
            Textarea::make('description')
                ->label(fn (Get $get): string => $isMonotype($get) ? 'Описание лодки' : 'Описание дивизиона')
                ->rows(3)
                ->maxLength(2000)
                ->columnSpanFull(),

            SpatieMediaLibraryFileUpload::make('gallery')
                ->label(fn (Get $get): string => $isMonotype($get) ? 'Фотографии лодки' : 'Фотографии дивизиона')
                ->helperText(fn (Get $get): string => $isMonotype($get)
                    ? 'Общая галерея для всех лодок дивизиона.'
                    : 'Общая галерея дивизиона: у каждой лодки своя галерея заводится в разделе «Услуги: Флот регат».')
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

            // Кнопка «Добавить яхту» — прямо в дивизионе, чтобы не уходить в
            // отдельный раздел. Не для монотипа без выбора яхт: там строки
            // заводит наблюдатель по `yachts_count`, и репитер стёр бы их как
            // «отсутствующие в состоянии формы»
            // (@see App\Observers\ForeignRegattaDivisionObserver).
            Repeater::make('yachts')
                ->label('Яхты дивизиона')
                ->helperText('Лодки разные, но цены и условия обычно повторяются: заполните первую и заводите остальные кнопкой «Дублировать» — останется сменить модель, название и то, что и правда отличается.')
                ->relationship()
                ->addActionLabel('Добавить яхту в дивизион')
                ->cloneable()
                ->reorderable()
                ->orderColumn('sort_order')
                ->collapsible()
                ->collapsed()
                ->defaultItems(0)
                ->itemLabel(fn (array $state): ?string => self::yachtItemLabel($state))
                ->schema(self::divisionYachtFields())
                ->columns(3)
                ->visible($picksYachts)
                ->columnSpanFull(),
        ];
    }

    /**
     * Пары «всего / занято» по каждому типу мест.
     *
     * Занятость ведётся руками: чартер сообщает, что место продано, а сайт
     * должен сразу показать «занято 4 из 5, осталось 1 место». Пустое «всего»
     * означает «вариант не объявлен» — тогда его вообще нет на витрине.
     *
     * @param  \Closure(Get): bool  $visible
     * @return list<Component>
     */
    private static function occupancyFields(\Closure $visible, string $prefix = ''): array
    {
        $fields = [];

        foreach (ParticipationOption::cases() as $option) {
            if (! $option->isSeatLike()) {
                continue;
            }

            $fields[] = TextInput::make($prefix.$option->totalColumn())
                ->label($option->label().' — всего')
                ->helperText('Пусто — вариант не продаётся.')
                ->numeric()
                ->minValue(0)
                ->maxValue(500)
                ->visible($visible);

            $fields[] = TextInput::make($prefix.$option->takenColumn())
                ->label($option->label().' — занято')
                ->numeric()
                ->minValue(0)
                ->maxValue(500)
                ->default(0)
                ->visible($visible);
        }

        return $fields;
    }

    /**
     * Поля лодки внутри дивизиона.
     *
     * Здесь только то, без чего лодку не опубликуешь: модель, название, год,
     * цены трёх вариантов, места и занятость. Галерея, описание, сборы, депозит
     * и заметки правятся в разделе «Услуги: Флот регат» — иначе форма регаты
     * превращается в простыню.
     *
     * @return list<Component>
     */
    private static function divisionYachtFields(): array
    {
        // Знак валюты: она одна на регату, а лодка лежит в репитере внутри
        // репитера — до неё четыре уровня вверх (поле, лодка, репитер лодок,
        // дивизион).
        $yachtCurrency = fn (Get $get): string => self::currencySymbol($get, '../../../../currency');

        // Цены у лодки свои только в гандикапном дивизионе: у монотипа с
        // выбором яхт они общие и заданы на дивизионе двумя уровнями выше.
        $hasOwnPrices = fn (Get $get): bool => ! (FleetDivisionType::tryFrom((string) $get('../../type'))?->sharesPrices() ?? false);

        return [
            Select::make('yacht_model_id')
                ->label('Модель из справочника')
                ->helperText('Нет нужной — заведите кнопкой «+».')
                ->options(fn (): array => self::yachtModelOptions())
                ->searchable()
                ->preload()
                ->live()
                ->createOptionForm(CharterYachtModelResource::fields(withGallery: false))
                ->createOptionUsing(fn (array $data): string => (string) CharterYachtModel::create($data)->getKey())
                ->required(fn (Get $get): bool => ! filled($get('model')))
                ->columnSpan(2),

            TextInput::make('name')
                ->label('Название лодки')
                ->placeholder('Nika')
                ->maxLength(255),

            TextInput::make('model')
                ->label('Модель (без справочника)')
                ->visible(fn (Get $get): bool => ! filled($get('yacht_model_id')))
                ->maxLength(255),

            TextInput::make('year')
                ->label('Год выпуска')
                ->numeric()
                ->minValue(1900)
                ->maxValue((int) now()->addYear()->format('Y')),

            TextInput::make('base_marina')
                ->label('База (марина)')
                ->placeholder('Marina Kaštela')
                ->maxLength(255),

            Select::make('status')
                ->label('Занятость (целиком)')
                ->helperText('Только про чартер целиком: места и каюты продаются и у занятой лодки.')
                ->options(CharterYachtStatus::options())
                ->default(CharterYachtStatus::Free->value)
                ->required(),

            Toggle::make('is_hidden')
                ->label('Не показывать на сайте')
                ->helperText('Убирает лодку со страницы регаты со всеми вариантами.')
                ->default(false),

            TextInput::make('price')
                ->label('Стоимость яхты целиком')
                ->helperText('Пусто — лодка целиком не сдаётся.')
                ->numeric()
                ->minValue(0)
                ->suffix($yachtCurrency)
                ->visible($hasOwnPrices),

            Select::make('price_unit')
                ->label('За что цена')
                ->options(CharterPriceUnit::options())
                ->default(CharterPriceUnit::Regatta->value)
                ->visible($hasOwnPrices),

            TextInput::make('seat_price')
                ->label('Стоимость места')
                ->numeric()
                ->minValue(0)
                ->suffix($yachtCurrency)
                ->visible($hasOwnPrices),

            TextInput::make('solo_seat_price')
                ->label('Стоимость места в одноместной каюте')
                ->numeric()
                ->minValue(0)
                ->suffix($yachtCurrency)
                ->visible($hasOwnPrices),

            TextInput::make('cabin_price')
                ->label('Стоимость двухместной каюты')
                ->numeric()
                ->minValue(0)
                ->suffix($yachtCurrency)
                ->visible($hasOwnPrices),

            TextInput::make('charter_fee')
                ->label('Сборы чартерной компании')
                ->numeric()
                ->minValue(0)
                ->suffix($yachtCurrency)
                ->visible($hasOwnPrices),

            TextInput::make('deposit')
                ->label('Депозит за яхту')
                ->numeric()
                ->minValue(0)
                ->suffix($yachtCurrency)
                ->visible($hasOwnPrices),

            TextInput::make('downwind_sail_price')
                ->label('Аренда спинакера / геннакера')
                ->numeric()
                ->minValue(0)
                ->suffix($yachtCurrency)
                ->visible($hasOwnPrices),

            TextInput::make('downwind_sail_deposit')
                ->label('Депозит за спинакер / геннакер')
                ->numeric()
                ->minValue(0)
                ->suffix($yachtCurrency)
                ->visible($hasOwnPrices),

            // Места считаются по типам и у самой лодки: дивизион с выбором яхт
            // общего пула не ведёт.
            ...self::occupancyFields(fn (Get $get): bool => true),

            TextInput::make('skipper_name')
                ->label('Шкипер')
                ->placeholder('Необязательно')
                ->maxLength(255)
                ->columnSpan(3),
        ];
    }

    /** @param  array<string, mixed>  $state */
    private static function yachtItemLabel(array $state): ?string
    {
        $model = trim((string) ($state['model'] ?? ''));

        if ($model === '' && filled($state['yacht_model_id'] ?? null)) {
            $model = (string) (CharterYachtModel::query()
                ->whereKey($state['yacht_model_id'])
                ->value('name') ?? '');
        }

        $name = trim((string) ($state['name'] ?? ''));

        $label = trim($model.($name === '' ? '' : ' «'.$name.'»'));

        if ($label === '') {
            return null;
        }

        $year = trim((string) ($state['year'] ?? ''));

        if ($year !== '') {
            $label .= ', '.$year;
        }

        // Занятость видно в свёрнутом списке: иначе, чтобы понять, что лодка
        // уже взята, пришлось бы раскрывать каждую.
        $status = $state['status'] ?? null;
        $status = $status instanceof CharterYachtStatus
            ? $status
            : CharterYachtStatus::tryFrom((string) $status);

        if ($status !== null && ! $status->isAvailable()) {
            $label .= ' — '.mb_strtolower($status->label());
        }

        return $label;
    }

    /**
     * Предупреждает про лодки, по которым на витрине нечего показать.
     *
     * Сохранить это не мешает: флот заводят раньше, чем приходят цены. Но если
     * после сохранения у лодки не горит ни одна кнопка
     * (@see App\Models\ForeignRegattaYacht::offeredParticipations()), админ
     * должен узнать об этом здесь, а не от посетителя.
     */
    public static function warnAboutSilentFleet(ForeignRegatta $record): void
    {
        $silent = $record->load('charterYachts.division')
            ->visibleCharterYachts()
            ->filter(fn ($yacht): bool => $yacht->offeredParticipations() === [])
            ->map(fn ($yacht): string => $yacht->title());

        if ($silent->isEmpty()) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Часть флота не видна на витрине')
            ->body('Ни одной кнопки не горит: '.$silent->take(5)->implode(', ')
                .($silent->count() > 5 ? ' и ещё '.($silent->count() - 5) : '')
                .'. Проверьте цены, свободные места и занятость.')
            ->send();
    }

    /**
     * Модели справочника для выпадающего списка.
     *
     * @return array<string, string>
     */
    private static function yachtModelOptions(): array
    {
        return CharterYachtModel::query()
            ->ordered()
            ->get()
            ->mapWithKeys(fn (CharterYachtModel $model): array => [
                (string) $model->getKey() => $model->label(),
            ])
            ->all();
    }

    /** @param  array<string, mixed>  $state */
    private static function divisionItemLabel(array $state): ?string
    {
        $name = trim((string) ($state['name'] ?? ''));
        $model = trim((string) ($state['model'] ?? ''));
        $count = (int) ($state['yachts_count'] ?? 0);

        $type = FleetDivisionType::tryFrom((string) ($state['type'] ?? ''));

        $spec = match (true) {
            $type?->usesYachtsCount() => trim($model.($count > 0 ? ' × '.$count : '')),
            $type === null => '',
            default => mb_strtolower($type->label()),
        };

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
                EditAction::make()
                    ->after(fn (ForeignRegatta $record) => self::warnAboutSilentFleet($record)),
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
