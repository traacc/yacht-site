<?php

declare(strict_types=1);

namespace App\Filament\Resources\RepairCases;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\RepairCases\Pages\ManageRepairCases;
use App\Models\RepairCase;
use App\Models\Yacht;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Кейсы подраздела «Ремонт и модернизация» раздела «Carter 30».
 */
class RepairCaseResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = RepairCase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Carter 30: Кейсы ремонта';

    protected static ?int $navigationSort = 29;

    protected static string|\UnitEnum|null $navigationGroup = 'Яхты';

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
        return 'Кейс ремонта';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Кейсы ремонта';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('title')
                            ->label('Название кейса')
                            ->placeholder('Например: Модернизация палубы «Ассоль»')
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

                        Select::make('yacht_id')
                            ->label('Яхта')
                            ->helperText('Необязательно: если кейс о конкретной яхте.')
                            // Yacht под глобальным скоупом OwnedScope (user_id IS NOT NULL),
                            // из-за него яхты без владельца в списке не появляются.
                            ->options(fn () => Yacht::withoutGlobalScopes()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Textarea::make('summary')
                            ->label('Краткое описание')
                            ->helperText('Показывается в карточке кейса на обзорной странице.')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Toggle::make('is_published')
                            ->label('Опубликован')
                            ->helperText('Неопубликованные кейсы не видны на сайте.')
                            ->default(false),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Section::make('Содержание')
                    ->description('Текст с картинками и чертежами в теле. Картинки добавляйте кнопкой «Прикрепить файлы»: при копировании из Word переносится только текст.')
                    ->schema([
                        RichEditor::make('content')
                            ->label('Текст кейса')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('carter30/repair')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsMaxSize(5120)
                            ->columnSpanFull(),
                    ]),

                Section::make('Обложка и чертежи')
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

                        SpatieMediaLibraryFileUpload::make('drawings')
                            ->label('Чертежи и документы')
                            ->collection('drawings')
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(20480)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'application/acad',
                                'image/vnd.dwg',
                                'application/dxf',
                                'image/vnd.dxf',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Фотографии')
                    ->description('Загрузите фото, затем задайте подписи ниже. Подписи выводятся под каждым снимком.')
                    ->schema([
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
                        // Намеренно НЕ ->relationship('galleryMedia'): репитер на
                        // связи при сохранении удаляет записи, которых нет в его
                        // состоянии (Repeater::saveToRelationship), а фотографии,
                        // загруженные загрузчиком выше в этот же submit, в состояние
                        // не попадают — их бы стёрло. Поэтому гидрируем и сохраняем
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
                                    $record instanceof RepairCase
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
                                if (! $record instanceof RepairCase) {
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
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('yacht.name')
                    ->label('Яхта')
                    ->placeholder('—')
                    ->searchable(),
                IconColumn::make('is_published')
                    ->label('Опубликован')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Изменён')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Кейсов пока нет')
            ->emptyStateDescription('Добавьте кейс ремонта — он появится на странице «Ремонт и модернизация».')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Кейс удалён'),
                    ),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('yacht');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRepairCases::route('/'),
        ];
    }
}
