<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tours;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\Tours\Pages\ManageTours;
use App\Models\Tour;
use App\Models\Yacht;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Filament\Schemas\Components\Section;
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
 * Походы подраздела «Яхтенные путешествия и походы» (раздел «Услуги»).
 *
 * Вводный текст страницы и общая галерея правятся в ServicesPageSettings —
 * здесь только сами походы.
 */
class TourResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = Tour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Услуги: Походы';

    protected static ?int $navigationSort = 30;

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

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
        return 'Поход';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Походы и путешествия';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('title')
                            ->label('Название похода')
                            ->placeholder('Например: Абхазия под парусом')
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
                            ->helperText('Показывается в карточке похода на витрине.')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Toggle::make('is_published')
                            ->label('Опубликован')
                            ->helperText('Неопубликованные походы не видны на сайте.')
                            ->default(false),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Section::make('Даты и маршрут')
                    ->schema([
                        DatePicker::make('date_start')
                            ->label('Дата начала')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y'),

                        DatePicker::make('date_end')
                            ->label('Дата окончания')
                            ->helperText('Пусто — однодневный выход.')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->afterOrEqual('date_start'),

                        TextInput::make('region')
                            ->label('Регион или акватория')
                            ->placeholder('Например: Абхазия')
                            ->maxLength(255),

                        TextInput::make('route_summary')
                            ->label('Маршрут одной строкой')
                            ->placeholder('Сочи — Гагра — Новый Афон — Сочи')
                            ->helperText('Показывается в карточке похода.')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('На чём идём')
                    ->description('Выберите яхту из реестра либо впишите судно вручную — для чартерной лодки, которой в реестре нет.')
                    ->schema([
                        Select::make('yacht_id')
                            ->label('Яхта из реестра')
                            // Yacht под глобальным скоупом OwnedScope (user_id IS NOT NULL),
                            // из-за него яхты без владельца в списке не появляются.
                            ->options(fn () => Yacht::withoutGlobalScopes()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        TextInput::make('vessel')
                            ->label('Судно (текстом)')
                            ->placeholder('Например: Bavaria 46, Хорватия')
                            ->helperText('Используется, только если яхта из реестра не выбрана.')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Цены и места')
                    ->schema([
                        TextInput::make('price_per_seat')
                            ->label('Цена за место')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽'),

                        TextInput::make('price_per_cabin')
                            ->label('Цена за каюту')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽'),

                        TextInput::make('org_fee')
                            ->label('Оргсбор')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽'),

                        TextInput::make('seats_total')
                            ->label('Всего мест')
                            ->numeric()
                            ->minValue(0),

                        TextInput::make('seats_left')
                            ->label('Осталось мест')
                            ->helperText('Ведётся вручную: автоматического списания по заявкам нет. Пусто — счётчик на сайте не показывается.')
                            ->numeric()
                            ->minValue(0),

                        Textarea::make('price_note')
                            ->label('Примечание к стоимости')
                            ->placeholder('Например: + судовая касса ~15 000 ₽ на человека')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Программа')
                    ->description('Текст с картинками в теле. Картинки добавляйте кнопкой «Прикрепить файлы»: при копировании из Word переносится только текст.')
                    ->schema([
                        RichEditor::make('content')
                            ->label('Программа похода')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('services/tours')
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
                                    $record instanceof Tour
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
                                if (! $record instanceof Tour) {
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label('Обложка')
                    ->collection('cover'),
                TextColumn::make('title')
                    ->label('Поход')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('date_start')
                    ->label('Даты')
                    ->state(fn (Tour $record): string => $record->dateRange())
                    ->sortable(),
                TextColumn::make('vessel')
                    ->label('На чём')
                    ->state(fn (Tour $record): ?string => $record->vesselLabel())
                    ->placeholder('—'),
                TextColumn::make('seats_left')
                    ->label('Места')
                    ->state(fn (Tour $record): string => $record->seats_left === null
                        ? '—'
                        : $record->seats_left.' / '.($record->seats_total ?? '—'))
                    ->tooltip('Осталось / всего'),
                TextColumn::make('price_per_seat')
                    ->label('Цена за место')
                    ->state(fn (Tour $record): ?string => $record->seatPriceLabel())
                    ->placeholder('—'),
                TextColumn::make('service_requests_count')
                    ->counts('serviceRequests')
                    ->label('Заявок'),
                IconColumn::make('is_published')
                    ->label('Опубликован')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Опубликован'),
                Filter::make('upcoming')
                    ->label('Только предстоящие')
                    ->query(fn (Builder $query): Builder => $query->upcoming()),
                Filter::make('past')
                    ->label('Только прошедшие')
                    ->query(fn (Builder $query): Builder => $query->past()),
            ])
            ->emptyStateHeading('Походов пока нет')
            ->emptyStateDescription('Добавьте поход — он появится на странице «Яхтенные путешествия и походы».')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Поход удалён'),
                    ),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['yacht', 'media']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTours::route('/'),
        ];
    }
}
