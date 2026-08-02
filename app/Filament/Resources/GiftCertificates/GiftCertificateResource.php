<?php

declare(strict_types=1);

namespace App\Filament\Resources\GiftCertificates;

use App\Enums\CertificatePriceType;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\GiftCertificates\Pages\ManageGiftCertificates;
use App\Models\GiftCertificate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Каталог подраздела «Подарочные сертификаты» (раздел «Услуги»).
 *
 * Вводный текст страницы, блок «Как это работает» и галерея правятся в
 * ServicesPageSettings — здесь только сами сертификаты. Заказы приходят в
 * «Заявки на услуги»: сертификат — объект заявки, а не отдельная сущность заказа.
 */
class GiftCertificateResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = GiftCertificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Услуги: Подарочные сертификаты';

    protected static ?int $navigationSort = 32;

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    /** Форматы, которые принимает загрузчик обложки (HEIC нормализуется в JPEG на лету). */
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
        return 'Подарочный сертификат';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Подарочные сертификаты';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('title')
                            ->label('Название сертификата')
                            ->placeholder('Например: Сертификат на выход в море')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', $state ? Str::slug($state) : '')),

                        TextInput::make('slug')
                            ->label('Slug (якорь на витрине)')
                            ->helperText('Ссылка на карточку сертификата в заявке ведёт на этот якорь.')
                            ->placeholder('avtomaticheski-zapolnyaetsya')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Textarea::make('summary')
                            ->label('Краткое описание')
                            ->helperText('Показывается в карточке сертификата на витрине.')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Toggle::make('is_published')
                            ->label('Опубликован')
                            ->helperText('Неопубликованный сертификат не виден на витрине и не принимает заказы.')
                            ->default(false),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Section::make('Стоимость')
                    ->description('При диапазоне заказчик выбирает номинал из списка: он собирается из границ и шага.')
                    ->schema([
                        Select::make('price_type')
                            ->label('Вид цены')
                            ->options(CertificatePriceType::options())
                            ->default(CertificatePriceType::Fixed->value)
                            ->required()
                            ->live(),

                        TextInput::make('price')
                            ->label('Стоимость сертификата')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽')
                            ->required(fn (Get $get): bool => $get('price_type') === CertificatePriceType::Fixed->value)
                            ->visible(fn (Get $get): bool => $get('price_type') === CertificatePriceType::Fixed->value),

                        TextInput::make('price_min')
                            ->label('Минимальный номинал')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽')
                            ->required(fn (Get $get): bool => $get('price_type') === CertificatePriceType::Range->value)
                            ->visible(fn (Get $get): bool => $get('price_type') === CertificatePriceType::Range->value),

                        TextInput::make('price_max')
                            ->label('Максимальный номинал')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('₽')
                            ->gte('price_min')
                            ->required(fn (Get $get): bool => $get('price_type') === CertificatePriceType::Range->value)
                            ->visible(fn (Get $get): bool => $get('price_type') === CertificatePriceType::Range->value),

                        TextInput::make('price_step')
                            ->label('Шаг номинала')
                            ->helperText('Слишком мелкий шаг будет укрупнён: список номиналов ограничен 50 позициями.')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('₽')
                            ->required(fn (Get $get): bool => $get('price_type') === CertificatePriceType::Range->value)
                            ->visible(fn (Get $get): bool => $get('price_type') === CertificatePriceType::Range->value),

                        TextInput::make('validity_months')
                            ->label('Срок действия')
                            ->helperText('В месяцах. Пусто — срок не указываем.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->suffix('мес.'),

                        Textarea::make('price_note')
                            ->label('Примечание к стоимости')
                            ->placeholder('Например: топливо и стоянка входят в стоимость')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Описание и условия')
                    ->description('Текст с картинками в теле. Картинки добавляйте кнопкой «Прикрепить файлы»: при копировании из Word переносится только текст.')
                    ->schema([
                        RichEditor::make('content')
                            ->label('Что входит в сертификат')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('services/gift-certificates')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsMaxSize(5120)
                            ->columnSpanFull(),

                        Textarea::make('terms')
                            ->label('Условия использования')
                            ->placeholder('Например: дата выхода согласуется заранее, перенос возможен не позднее чем за сутки')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Обложка')
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
                    ->label('Сертификат')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('price_type')
                    ->label('Вид цены')
                    ->badge()
                    ->formatStateUsing(fn (CertificatePriceType $state): string => $state->label()),
                TextColumn::make('price')
                    ->label('Стоимость')
                    ->state(fn (GiftCertificate $record): string => $record->priceLabel()),
                TextColumn::make('validity_months')
                    ->label('Срок действия')
                    ->state(fn (GiftCertificate $record): ?string => $record->validityLabel())
                    ->placeholder('—'),
                TextColumn::make('service_requests_count')
                    ->counts('serviceRequests')
                    ->label('Заказов'),
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
                SelectFilter::make('price_type')
                    ->label('Вид цены')
                    ->options(CertificatePriceType::options()),
            ])
            ->emptyStateHeading('Сертификатов пока нет')
            ->emptyStateDescription('Добавьте сертификат — он появится в каталоге на странице «Подарочные сертификаты».')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Сертификат удалён'),
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
            'index' => ManageGiftCertificates::route('/'),
        ];
    }
}
