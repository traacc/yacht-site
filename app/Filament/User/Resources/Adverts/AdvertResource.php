<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Adverts;

use App\Enums\AdvertStatus;
use App\Enums\AdvertType;
use App\Filament\User\Resources\Adverts\Pages\ManageAdverts;
use App\Models\Advert;
use App\Models\AdvertCategory;
use App\Models\Yacht;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * «Мои объявления» в личном кабинете.
 *
 * Подача, правка и снятие с публикации. Правка опубликованного объявления
 * возвращает его на модерацию — иначе премодерация обходится тривиально.
 */
class AdvertResource extends Resource
{
    protected static ?string $model = Advert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Мои объявления';

    protected static ?int $navigationSort = 6;

    /** Форматы, которые принимает загрузчик (HEIC нормализуется в JPEG на лету). */
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
        return 'Объявление';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Мои объявления';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Объявление')
                ->schema([
                    Select::make('type')
                        ->label('Раздел')
                        ->options(AdvertType::options())
                        ->default(AdvertType::Marketplace->value)
                        ->required()
                        ->live()
                        // Тип определяет, какие поля показывать, поэтому у уже
                        // созданного объявления его не меняем.
                        ->disabledOn('edit'),

                    Select::make('advert_category_id')
                        ->label('Категория')
                        ->options(fn (Get $get): array => static::categoryOptions($get('type')))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn (Get $get): bool => static::typeFrom($get('type'))?->usesCategories() ?? false),

                    Select::make('yacht_id')
                        ->label('Яхта')
                        ->helperText('Выберите одну из своих зарегистрированных яхт.')
                        ->options(fn (): array => Yacht::query()
                            ->where('user_id', auth()->id())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn (Get $get): bool => static::typeFrom($get('type'))?->usesYacht() ?? false),

                    TextInput::make('title')
                        ->label('Заголовок')
                        ->placeholder('Например: Комплект парусов Carter 30')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('Описание')
                        ->placeholder('Опишите товар: состояние, комплектность, причину продажи')
                        ->required()
                        ->rows(6)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Цена и местонахождение')
                ->schema([
                    TextInput::make('price')
                        ->label('Цена, ₽')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(999999999)
                        ->requiredIfAccepted('price_negotiable')
                        // «Договорная» — единственный случай, когда сумму можно не указывать.
                        ->required(fn (Get $get): bool => ! $get('price_negotiable'))
                        ->helperText('Можно не указывать, если цена договорная.'),

                    Toggle::make('price_negotiable')
                        ->label('Цена договорная')
                        ->live()
                        ->default(false),

                    TextInput::make('city')
                        ->label('Город')
                        ->placeholder('Москва')
                        ->maxLength(255),
                ])
                ->columns(2),

            Section::make('Контакты для публикации')
                ->description('Показываются в объявлении. Все поля необязательные — если не заполнить, с вами свяжутся через сайт.')
                ->schema([
                    TextInput::make('contact_phone')
                        ->label('Телефон')
                        // tel() обязателен: именно он вешает правило regex,
                        // telRegex() в одиночку лишь задаёт паттерн и ничего
                        // не проверяет на сервере.
                        ->tel()
                        ->telRegex('/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/')
                        ->mask('+7 (999) 999-99-99')
                        ->placeholder('+7 (___) ___-__-__')
                        ->validationMessages(['regex' => 'Укажите телефон в формате +7 (999) 123-45-67.'])
                        ->maxLength(255),

                    TextInput::make('contact_telegram')
                        ->label('Telegram')
                        ->placeholder('@nickname')
                        ->rule('regex:/^@[A-Za-z0-9_]{5,32}$/')
                        ->validationMessages(['regex' => 'Укажите ник в формате @nickname (5–32 символа: латиница, цифры, подчёркивание).'])
                        ->maxLength(255),

                    TextInput::make('contact_email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),
                ])
                ->columns(3),

            Section::make('Фотографии')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('photos')
                        ->label('Фотографии')
                        ->collection(Advert::PHOTOS)
                        ->multiple()
                        ->reorderable()
                        ->image()
                        ->acceptedFileTypes(self::IMAGE_MIMES)
                        ->imageEditor()
                        ->disk('public')
                        ->visibility('public')
                        ->maxSize(10240)
                        ->maxFiles(fn (Get $get): int => static::typeFrom($get('type'))?->maxPhotos() ?? 10)
                        ->panelLayout('grid')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('Фото')
                    ->collection(Advert::PHOTOS)
                    ->limit(1),
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->wrap()
                    ->limit(60),
                TextColumn::make('type')
                    ->label('Раздел')
                    ->badge()
                    ->formatStateUsing(fn (AdvertType $state): string => $state->label()),
                TextColumn::make('price')
                    ->label('Цена')
                    ->state(fn (Advert $record): string => $record->priceLabel()),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (AdvertStatus $state): string => $state->label())
                    ->color(fn (AdvertStatus $state): string => $state->color())
                    ->description(fn (Advert $record): ?string => $record->rejection_reason),
                TextColumn::make('created_at')
                    ->label('Подано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(AdvertStatus::options()),
            ])
            ->emptyStateHeading('У вас пока нет объявлений')
            ->emptyStateDescription('Разместите объявление — после проверки модератором оно появится на сайте.')
            ->recordActions([
                Action::make('open')
                    ->label('На сайте')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Advert $record): string => $record->publicUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Advert $record): bool => $record->isVisible()),

                EditAction::make()
                    ->label('Изменить')
                    ->modalHeading('Изменить объявление')
                    ->after(function (Advert $record): void {
                        // Правка возвращает объявление на модерацию — иначе
                        // премодерацию можно обойти правкой после публикации.
                        if ($record->status === AdvertStatus::Pending) {
                            return;
                        }

                        $record->sendToModeration();

                        Notification::make()
                            ->title('Объявление отправлено на повторную модерацию')
                            ->warning()
                            ->send();
                    }),

                Action::make('markSold')
                    ->label('Продано')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Отметить как проданное?')
                    ->modalDescription('Объявление останется на сайте с пометкой «Продано».')
                    ->visible(fn (Advert $record): bool => $record->isPublished())
                    ->action(function (Advert $record): void {
                        $record->markSold();

                        Notification::make()
                            ->success()
                            ->title('Объявление отмечено как проданное')
                            ->send();
                    }),

                Action::make('archive')
                    ->label('Снять')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Снять объявление с публикации?')
                    ->modalDescription('Оно исчезнет с сайта, но останется у вас — можно будет отправить на модерацию снова.')
                    ->visible(fn (Advert $record): bool => $record->isVisible())
                    ->action(function (Advert $record): void {
                        $record->archive();

                        Notification::make()
                            ->success()
                            ->title('Объявление снято с публикации')
                            ->send();
                    }),

                Action::make('republish')
                    ->label('Опубликовать снова')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Отправить на модерацию?')
                    ->visible(fn (Advert $record): bool => in_array(
                        $record->status,
                        [AdvertStatus::Archived, AdvertStatus::Sold, AdvertStatus::Rejected],
                        true,
                    ))
                    ->action(function (Advert $record): void {
                        $record->sendToModeration();

                        Notification::make()
                            ->success()
                            ->title('Объявление отправлено на модерацию')
                            ->send();
                    }),

                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Удалить объявление?')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Объявление удалено'),
                    ),
            ]);
    }

    /** @return array<string, string> */
    private static function categoryOptions(mixed $type): array
    {
        $type = static::typeFrom($type);

        if (! $type instanceof AdvertType) {
            return [];
        }

        return AdvertCategory::query()
            ->ofType($type)
            ->ordered()
            ->pluck('title', 'id')
            ->all();
    }

    /** Состояние Select'а приходит строкой, а на edit — уже enum'ом. */
    private static function typeFrom(mixed $state): ?AdvertType
    {
        if ($state instanceof AdvertType) {
            return $state;
        }

        return is_string($state) ? AdvertType::tryFrom($state) : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdverts::route('/'),
        ];
    }
}
