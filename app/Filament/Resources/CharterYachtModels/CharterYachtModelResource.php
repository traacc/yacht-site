<?php

declare(strict_types=1);

namespace App\Filament\Resources\CharterYachtModels;

use App\Enums\DownwindSail;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\CharterYachtModels\Pages\ManageCharterYachtModels;
use App\Models\CharterYachtModel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Справочник моделей чартерных яхт.
 *
 * Общий на все зарубежные регаты: описание и галерея лодки не зависят от того,
 * в какой регате её взяли, поэтому заводятся один раз и переиспользуются
 * (@see App\Models\CharterYachtModel).
 *
 * Заводить модель отсюда необязательно — она создаётся прямо из формы регаты
 * кнопкой «+» рядом с выбором модели; этот раздел нужен, чтобы дополнить
 * описание, галерею и страну уже накопленных моделей.
 */
class CharterYachtModelResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = CharterYachtModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Услуги: Справочник яхт';

    protected static ?int $navigationSort = 33;

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
        return 'Модель яхты';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Справочник моделей яхт';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Модель')
                    ->description('Общее для всех лодок этой модели. Год выпуска и название здесь не указываются — они у каждой лодки свои. Цены тоже: они зависят от регаты и вводятся в её дивизионе.')
                    ->schema(self::fields())
                    ->columns(2),
            ]);
    }

    /**
     * Поля модели.
     *
     * Вынесены отдельно, потому что та же форма открывается из выбора модели в
     * регате (`createOptionForm`): заводить модель удобнее не уходя с флота.
     * Галерею там не показываем — записи ещё нет, медиатеке некуда складывать
     * файлы; фотографии добавляются в самом справочнике.
     *
     * @return list<Component>
     */
    public static function fields(bool $withGallery = true): array
    {
        return array_values(array_filter([
            TextInput::make('name')
                ->label('Модель')
                ->placeholder('Bavaria 46')
                ->required()
                ->maxLength(255),

            TextInput::make('country')
                ->label('Страна базирования')
                ->helperText('Пусто — подставится страна регаты, где модель используют впервые.')
                ->maxLength(255),

            TextInput::make('cabins')
                ->label('Кают')
                ->numeric()
                ->minValue(1)
                ->maxValue(20),

            Select::make('downwind_sail')
                ->label('Спинакер / геннакер')
                ->options(DownwindSail::options()),

            Textarea::make('description')
                ->label('Описание')
                ->rows(4)
                ->maxLength(2000)
                ->columnSpanFull(),

            ! $withGallery ? null : SpatieMediaLibraryFileUpload::make('gallery')
                ->label('Фотографии')
                ->helperText('Показываются на карточке каждой лодки этой модели, если у лодки нет своих.')
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
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('gallery')
                    ->label('Фото')
                    ->collection('gallery')
                    ->limit(1),

                TextColumn::make('name')
                    ->label('Модель')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('country')
                    ->label('Страна')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('cabins')
                    ->label('Кают')
                    ->placeholder('—'),

                TextColumn::make('yachts_count')
                    ->label('Лодок во флотах')
                    ->counts('yachts'),

            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('country')
                    ->label('Страна')
                    ->options(fn (): array => CharterYachtModel::query()
                        ->whereNotNull('country')
                        ->distinct()
                        ->orderBy('country')
                        ->pluck('country', 'country')
                        ->all()),
            ])
            ->emptyStateHeading('Справочник пуст')
            ->emptyStateDescription('Модели попадают сюда сами, когда их заводят во флоте регаты.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    // Лодки и дивизионы ссылку теряют (nullOnDelete), но свои
                    // название, год и цены сохраняют — витрина не рассыпается.
                    ->modalDescription('Модель исчезнет из выбора. У заведённых лодок останутся их собственные данные, но описание и галерея модели с карточек пропадут.')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Модель удалена'),
                    ),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCharterYachtModels::route('/'),
        ];
    }
}
