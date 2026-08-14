<?php

declare(strict_types=1);

namespace App\Filament\Resources\ForeignRegattaYachts;

use App\Enums\CharterPriceUnit;
use App\Enums\CharterYachtStatus;
use App\Enums\DownwindSail;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\ForeignRegattaYachts\Pages\ManageForeignRegattaYachts;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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

                        TextInput::make('name')
                            ->label('Название лодки')
                            ->placeholder('Nika')
                            ->maxLength(255),

                        TextInput::make('model')
                            ->label('Модель')
                            ->placeholder('Bavaria 46')
                            ->required(fn (Get $get): bool => ! self::inheritsSpec($get))
                            ->maxLength(255),

                        TextInput::make('cabins')
                            ->label('Кают')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required(fn (Get $get): bool => ! self::inheritsSpec($get)),

                        TextInput::make('year')
                            ->label('Год выпуска')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue((int) now()->addYear()->format('Y')),

                        Select::make('downwind_sail')
                            ->label('Спинакер / геннакер')
                            ->options(DownwindSail::options()),

                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3),

                Section::make('Стоимость чартера')
                    ->description('Цена лодки целиком. Пустые поля берутся у дивизиона-флота.')
                    ->schema([
                        TextInput::make('price')
                            ->label('Стоимость')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽')
                            ->required(fn (Get $get): bool => ! self::inheritsSpec($get)),

                        Select::make('price_unit')
                            ->label('За что цена')
                            ->options(CharterPriceUnit::options()),

                        Select::make('status')
                            ->label('Занятость')
                            ->helperText('Лодка без шкипера сдаётся целиком — кнопка «Хочу эту яхту» горит, пока она свободна.')
                            ->options(CharterYachtStatus::options())
                            ->default(CharterYachtStatus::Free->value)
                            ->required(),

                        TextInput::make('charter_fee')
                            ->label('Сборы чартерной компании')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽'),

                        TextInput::make('deposit')
                            ->label('Депозит')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽'),

                        TextInput::make('price_note')
                            ->label('Примечание к стоимости')
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Section::make('Шкипер и места в экипаже')
                    ->description('Шкипер указан — лодка идёт со своим капитаном и продаёт места: на витрине появляется кнопка «Хочу в экипаж». Шкипера нет — лодка сдаётся целиком.')
                    ->schema([
                        TextInput::make('skipper_name')
                            ->label('Шкипер')
                            ->placeholder('Иван Петров')
                            ->maxLength(255),

                        TextInput::make('free_seats')
                            ->label('Свободных мест')
                            ->helperText('0 или пусто — кнопка «Хочу в экипаж» не горит.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50),

                        TextInput::make('seat_price')
                            ->label('Стоимость места')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽'),

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
                    ->description('Пусто — на карточке покажется описание и галерея дивизиона.')
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

                TextColumn::make('free_seats')
                    ->label('Мест')
                    ->state(fn (ForeignRegattaYacht $record): string => $record->hasSkipper()
                        ? (string) $record->freeSeats()
                        : '—')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Чартер')
                    ->state(fn (ForeignRegattaYacht $record): ?string => $record->priceLabel())
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Занятость')
                    ->badge()
                    ->formatStateUsing(fn (CharterYachtStatus $state): string => $state->label())
                    ->color(fn (CharterYachtStatus $state): string => $state->color()),

                // Что увидит посетитель на карточке этой лодки: правило кнопки
                // выводится из шкипера, мест и занятости — не из отдельного поля.
                TextColumn::make('cta')
                    ->label('Кнопка на сайте')
                    ->state(fn (ForeignRegattaYacht $record): string => $record->ctaLabel() ?? 'нет')
                    ->badge()
                    ->color(fn (ForeignRegattaYacht $record): string => $record->ctaLabel() === null ? 'gray' : 'success'),
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

                Filter::make('selling_seats')
                    ->label('Набирают экипаж')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('skipper_name')
                        ->where('skipper_name', '!=', '')
                        ->where('free_seats', '>', 0)),

                Filter::make('whole_charter')
                    ->label('Сдаются целиком')
                    ->query(fn (Builder $query): Builder => $query
                        ->where(fn (Builder $inner) => $inner
                            ->whereNull('skipper_name')
                            ->orWhere('skipper_name', ''))
                        ->where('status', CharterYachtStatus::Free->value)),
            ])
            ->emptyStateHeading('Лодок пока нет')
            ->emptyStateDescription('Добавьте дивизион в форме регаты — лодки флота создадутся сами, либо заведите лодку здесь вручную.')
            ->recordActions([
                EditAction::make(),
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

    /** Берёт ли лодка характеристики у дивизиона — тогда свои поля необязательны. */
    private static function inheritsSpec(Get $get): bool
    {
        return self::division($get)?->sharesSpec() ?? false;
    }

    /** Что именно унаследуется — одной строкой в описании секции. */
    private static function inheritanceHint(Get $get): string
    {
        $division = self::division($get);

        if ($division === null || ! $division->sharesSpec()) {
            return 'Характеристики этой лодки заполняются здесь.';
        }

        $spec = array_filter([
            trim((string) $division->model),
            $division->cabins === null ? null : $division->cabins.' кают',
            $division->year === null ? null : (string) $division->year,
            $division->price === null ? null : number_format((float) $division->price, 0, ',', ' ').' ₽',
        ]);

        return 'Пустые поля берутся из дивизиона «'.$division->title().'»'
            .($spec === [] ? '.' : ': '.implode(', ', $spec).'.');
    }

    private static function division(Get $get): ?ForeignRegattaDivision
    {
        $divisionId = $get('division_id');

        return blank($divisionId)
            ? null
            : ForeignRegattaDivision::query()->find($divisionId);
    }
}
