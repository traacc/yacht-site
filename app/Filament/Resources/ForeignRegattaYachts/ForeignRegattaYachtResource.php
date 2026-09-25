<?php

declare(strict_types=1);

namespace App\Filament\Resources\ForeignRegattaYachts;

use App\Enums\CharterPriceUnit;
use App\Enums\CharterYachtStatus;
use App\Enums\Currency;
use App\Enums\DownwindSail;
use App\Enums\FleetDivisionType;
use App\Enums\ParticipationOption;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\CharterYachtModels\CharterYachtModelResource;
use App\Filament\Resources\ForeignRegattaYachts\Pages\ManageForeignRegattaYachts;
use App\Models\CharterYachtModel;
use App\Models\ForeignRegatta;
use App\Models\ForeignRegattaDivision;
use App\Models\ForeignRegattaYacht;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Лодки зарубежных регат: шкиперы, свободные места и занятость.
 *
 * Отдельно от формы регаты по двум причинам. Во-первых, лодок бывает несколько
 * десятков, и таблица со шкиперами и местами читается лучше репитера.
 * Во-вторых, лодки дивизиона-флота заводит наблюдатель по `yachts_count`
 * (@see App\Actions\Service\SyncFleetDivisionYachts), а вложенный репитер стёр
 * бы их при сохранении как «отсутствующие в состоянии формы».
 *
 * Дивизионы правятся в форме самой регаты.
 */
class ForeignRegattaYachtResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = ForeignRegattaYacht::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Услуги: Флот регат';

    protected static ?int $navigationSort = 32;

    protected static string|\UnitEnum|null $navigationGroup = 'Услуги';

    /** @see ForeignRegattaResource::IMAGE_MIMES — HEIC нормализуется в JPEG на лету. */
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
        return 'Яхта регаты';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Флот зарубежных регат';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Лодка')
                    ->description(fn (Get $get): string => self::inheritanceHint($get))
                    ->schema([
                        Select::make('foreign_regatta_id')
                            ->label('Регата')
                            ->options(fn (): array => ForeignRegatta::query()
                                ->orderByDesc('date_start')
                                ->pluck('title', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->live()
                            // Дивизионы у каждой регаты свои: при смене регаты
                            // выбранный дивизион перестал бы ей принадлежать.
                            ->afterStateUpdated(fn (callable $set) => $set('division_id', null)),

                        Select::make('division_id')
                            ->label('Дивизион')
                            ->helperText('Пусто — лодка показывается вне дивизионов.')
                            ->options(fn (Get $get): array => self::divisionOptions($get('foreign_regatta_id')))
                            ->live()
                            ->nullable(),

                        Select::make('yacht_model_id')
                            ->label('Модель из справочника')
                            ->helperText('Описание, фотографии и каюты возьмутся отсюда. Нет нужной модели — заведите кнопкой «+».')
                            ->options(fn (): array => CharterYachtModel::query()
                                ->ordered()
                                ->get()
                                ->mapWithKeys(fn (CharterYachtModel $model): array => [
                                    (string) $model->getKey() => $model->label(),
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->createOptionForm(CharterYachtModelResource::fields(withGallery: false))
                            ->createOptionUsing(fn (array $data): string => (string) CharterYachtModel::create($data)->getKey()),

                        TextInput::make('name')
                            ->label('Название лодки')
                            ->placeholder('Nika')
                            ->maxLength(255),

                        TextInput::make('model')
                            ->label('Модель (без справочника)')
                            ->placeholder('Bavaria 46')
                            ->required(fn (Get $get): bool => ! self::inheritsSpec($get) && ! filled($get('yacht_model_id')))
                            ->visible(fn (Get $get): bool => ! filled($get('yacht_model_id')))
                            ->maxLength(255),

                        TextInput::make('cabins')
                            ->label('Кают')
                            ->helperText('Необязательно — просто не попадёт в характеристики на витрине.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->visible(fn (Get $get): bool => ! filled($get('yacht_model_id'))),

                        TextInput::make('year')
                            ->label('Год выпуска')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue((int) now()->addYear()->format('Y')),

                        Select::make('downwind_sail')
                            ->label('Спинакер / геннакер')
                            ->options(DownwindSail::options()),

                        TextInput::make('base_marina')
                            ->label('База (марина)')
                            ->placeholder('Marina Kaštela')
                            ->maxLength(255),

                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3),

                Section::make('Стоимость чартера')
                    ->description('Цена лодки целиком: заполнена — на витрине горит кнопка «Яхта целиком», пусто — лодка целиком не сдаётся и предлагаются только места и каюты. Пустые поля берутся у дивизиона-флота.')
                    ->schema([
                        TextInput::make('price')
                            ->label('Стоимость яхты целиком')
                            ->helperText('Пусто — лодка целиком не сдаётся.')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

                        Select::make('price_unit')
                            ->label('За что цена')
                            ->options(CharterPriceUnit::options()),

                        Select::make('status')
                            ->label('Занятость (целиком)')
                            ->helperText('Только про чартер целиком: занятая лодка продолжает продавать места и каюты, пока они есть.')
                            ->options(CharterYachtStatus::options())
                            ->default(CharterYachtStatus::Free->value)
                            ->required(),

                        Toggle::make('is_hidden')
                            ->label('Не показывать на сайте')
                            ->helperText('Убирает лодку со страницы регаты целиком — со всеми вариантами.')
                            ->default(false),

                        TextInput::make('charter_fee')
                            ->label('Сборы чартерной компании')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

                        TextInput::make('deposit')
                            ->label('Депозит')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

                        TextInput::make('downwind_sail_price')
                            ->label('Аренда спинакера / геннакера')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

                        TextInput::make('downwind_sail_deposit')
                            ->label('Депозит за спинакер / геннакер')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

                        TextInput::make('price_note')
                            ->label('Примечание к стоимости')
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Section::make('Шкипер, места и занятость')
                    ->description('Каждый тип мест продаётся отдельно: у него своя цена и свой счётчик «всего / занято». Пустое «всего» означает, что вариант не предлагается; когда занято сравнялось с общим числом, витрина пишет «всё занято». Шкипер на продажу мест не влияет — он просто показывается на карточке.')
                    ->schema([
                        TextInput::make('skipper_name')
                            ->label('Шкипер')
                            ->placeholder('Иван Петров')
                            ->maxLength(255),

                        TextInput::make('seat_price')
                            ->label('Стоимость места в двухместной каюте')
                            ->helperText('Пусто здесь и у дивизиона — вариант не продаётся.')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

                        TextInput::make('solo_seat_price')
                            ->label('Стоимость места в одноместной каюте')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

                        TextInput::make('cabin_price')
                            ->label('Стоимость двухместной каюты')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::currencySymbol($get)),

                        ...self::occupancyFields(),

                        TextInput::make('skipper_note')
                            ->label('О шкипере')
                            ->placeholder('Например: капитан категории Bareboat Skipper, 12 регат')
                            ->maxLength(255)
                            ->columnSpan(2),

                        TextInput::make('seat_note')
                            ->label('Комментарий к местам')
                            ->placeholder('Например: две койки в носовой каюте')
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Section::make('Описание и фотографии')
                    ->description('Пусто — на карточке покажется описание и галерея дивизиона, а если и там пусто — модели из справочника.')
                    ->schema([
                        Textarea::make('description')
                            ->label('Описание лодки')
                            ->rows(3)
                            ->maxLength(2000)
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
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('gallery')
                    ->label('Фото')
                    ->collection('gallery')
                    ->limit(1),

                TextColumn::make('regatta.title')
                    ->label('Регата')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('division_id')
                    ->label('Дивизион')
                    ->state(fn (ForeignRegattaYacht $record): ?string => $record->division?->title())
                    ->placeholder('—'),

                TextColumn::make('name')
                    ->label('Яхта')
                    ->state(fn (ForeignRegattaYacht $record): string => $record->title())
                    ->searchable(['name', 'model'])
                    ->wrap(),

                TextColumn::make('skipper_name')
                    ->label('Шкипер')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('occupancy')
                    ->label('Места')
                    ->state(fn (ForeignRegattaYacht $record): array => array_values(array_filter(
                        array_map(
                            fn (ParticipationOption $option): ?string => $option->isSeatLike()
                                && $record->countsOccupancy($option)
                                ? $option->shortLabel().': '.$record->occupancyShortLabel($option)
                                : null,
                            ParticipationOption::cases(),
                        ),
                    )))
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('price')
                    ->label('Чартер')
                    ->state(fn (ForeignRegattaYacht $record): ?string => $record->priceLabel())
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Целиком')
                    ->badge()
                    ->formatStateUsing(fn (CharterYachtStatus $state): string => $state->label())
                    ->color(fn (CharterYachtStatus $state): string => $state->color()),

                IconColumn::make('is_hidden')
                    ->label('Скрыта')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedEyeSlash)
                    ->falseIcon(Heroicon::OutlinedEye)
                    ->trueColor('danger')
                    ->falseColor('gray'),

                // Что увидит посетитель на карточке этой лодки: набор кнопок
                // выводится из шкипера, мест, цен и занятости — не из отдельного поля.
                /*
                TextColumn::make('cta')
                    ->label('Кнопки на сайте')
                    ->state(fn (ForeignRegattaYacht $record): array => array_map(
                        fn (ParticipationOption $option): string => $option->shortLabel(),
                        $record->offeredParticipations(),
                    ))
                    ->badge()
                    ->placeholder('нет'),
                */
            ])
            ->defaultGroup('regatta.title')
            ->groups([
                Group::make('regatta.title')->label('Регата'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('foreign_regatta_id')
                    ->label('Регата')
                    ->options(fn (): array => ForeignRegatta::query()
                        ->orderByDesc('date_start')
                        ->pluck('title', 'id')
                        ->all())
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Занятость')
                    ->options(CharterYachtStatus::options()),

                // Фильтры повторяют правила витрины: вариант предлагается, если
                // заполнена его цена — своя или унаследованная от монотипного
                // дивизиона (@see ForeignRegattaYacht::offeredParticipations()).
                Filter::make('selling_seats')
                    ->label('Продают места')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('is_hidden', false)
                        ->whereRaw('COALESCE(seats_taken, 0) < COALESCE(seats_total, 0)')
                        ->where(fn (Builder $inner) => $inner
                            ->whereNotNull('seat_price')
                            ->orWhereHas('division', fn (Builder $division) => $division
                                ->whereIn('type', FleetDivisionType::sharingPrices())
                                ->whereNotNull('seat_price')))),

                Filter::make('whole_charter')
                    ->label('Сдаются целиком')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('is_hidden', false)
                        ->where('status', CharterYachtStatus::Free->value)
                        ->where(fn (Builder $inner) => $inner
                            ->whereNotNull('price')
                            ->orWhereHas('division', fn (Builder $division) => $division
                                ->whereIn('type', FleetDivisionType::sharingPrices())
                                ->whereNotNull('price')))),

                Filter::make('hidden')
                    ->label('Сняты с витрины')
                    ->query(fn (Builder $query): Builder => $query->where('is_hidden', true)),
            ])
            ->emptyStateHeading('Лодок пока нет')
            ->emptyStateDescription('Добавьте дивизион в форме регаты — лодки флота создадутся сами, либо заведите лодку здесь вручную.')
            ->recordActions([
                EditAction::make()
                    ->after(fn (ForeignRegattaYacht $record) => self::warnIfNothingOffered($record)),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Яхта удалена'),
                    ),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['regatta', 'division', 'media']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageForeignRegattaYachts::route('/'),
        ];
    }

    // ──────────────────────────────────────────────
    // Наследование характеристик от дивизиона
    // ──────────────────────────────────────────────

    /** @return array<string, string> */
    private static function divisionOptions(mixed $regattaId): array
    {
        if (blank($regattaId)) {
            return [];
        }

        return ForeignRegattaDivision::query()
            ->where('foreign_regatta_id', $regattaId)
            ->ordered()
            ->get()
            ->mapWithKeys(fn (ForeignRegattaDivision $division): array => [
                (string) $division->getKey() => $division->title()
                    .' ('.$division->type->label().')',
            ])
            ->all();
    }

    /**
     * Знак валюты в суффиксах полей суммы.
     *
     * Валюта одна на регату, поэтому берётся у выбранной в форме регаты:
     * запись в этот момент ещё не сохранена.
     */
    private static function currencySymbol(Get $get): string
    {
        return Currency::fromNullable(self::regatta($get)?->currency)->symbol();
    }

    /** Берёт ли лодка характеристики у дивизиона — тогда свои поля необязательны. */
    private static function inheritsSpec(Get $get): bool
    {
        return self::division($get)?->sharesSpec() ?? false;
    }

    /**
     * Пары «всего / занято» по каждому типу мест.
     *
     * Счётчики у лодки свои и от дивизиона не наследуются: там, где дивизион
     * ведёт общий пул мест, лодки их не продают
     * (@see App\Models\ForeignRegattaDivision::sellsDirectly()).
     *
     * @return list<Component>
     */
    private static function occupancyFields(): array
    {
        $fields = [];

        foreach (ParticipationOption::cases() as $option) {
            if (! $option->isSeatLike()) {
                continue;
            }

            $fields[] = TextInput::make($option->totalColumn())
                ->label($option->label().' — всего')
                ->helperText('Пусто — вариант не продаётся.')
                ->numeric()
                ->minValue(0)
                ->maxValue(500);

            $fields[] = TextInput::make($option->takenColumn())
                ->label($option->label().' — занято')
                ->numeric()
                ->minValue(0)
                ->maxValue(500)
                ->default(0);
        }

        return $fields;
    }

    /** Берёт ли лодка цены у дивизиона: у обоих монотипов они общие. */
    private static function inheritsPrices(Get $get): bool
    {
        return self::division($get)?->sharesPrices() ?? false;
    }

    /**
     * Предупреждает, что по лодке нечего предложить.
     *
     * Именно предупреждает, а не запрещает сохранить: флот заводят задолго до
     * того, как чартер пришлёт цены, и блокировать ввод из-за пустого поля
     * нельзя. Но лодка без единой цены не покажет на витрине ни одной кнопки
     * (@see ForeignRegattaYacht::offeredParticipations()), и знать об этом
     * админ должен сразу.
     */
    public static function warnIfNothingOffered(ForeignRegattaYacht $record): void
    {
        $record->refresh()->load('division');

        if (! $record->isPublished() || $record->offeredParticipations() !== []) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('По лодке нечего предложить')
            ->body($record->title().': ни один вариант не горит на витрине. Проверьте цены (за яхту целиком, за место, за каюту), свободные места и занятость.')
            ->send();
    }

    /** Что именно унаследуется — одной строкой в описании секции. */
    private static function inheritanceHint(Get $get): string
    {
        $division = self::division($get);

        if ($division === null || (! $division->sharesSpec() && ! $division->sharesPrices())) {
            return 'Характеристики и цены этой лодки заполняются здесь.';
        }

        // Монотип с выбором яхт делится только ценой: модель у каждой лодки своя.
        $spec = array_filter([
            $division->sharesSpec() ? trim((string) $division->model) : null,
            $division->sharesSpec() && $division->cabins !== null ? $division->cabins.' кают' : null,
            $division->sharesSpec() && $division->year !== null ? (string) $division->year : null,
            $division->price === null ? null : $division->priceCurrency()->format($division->price),
            $division->seat_price === null ? null : $division->priceCurrency()->format($division->seat_price).' за место',
        ]);

        $what = $division->sharesSpec() ? 'Пустые поля' : 'Цены';

        return $what.' берутся из дивизиона «'.$division->title().'»'
            .($spec === [] ? '.' : ': '.implode(', ', $spec).'.');
    }

    private static function regatta(Get $get): ?ForeignRegatta
    {
        $regattaId = $get('foreign_regatta_id');

        return blank($regattaId)
            ? null
            : ForeignRegatta::query()->find($regattaId);
    }

    private static function division(Get $get): ?ForeignRegattaDivision
    {
        $divisionId = $get('division_id');

        return blank($divisionId)
            ? null
            : ForeignRegattaDivision::query()->find($divisionId);
    }
}
