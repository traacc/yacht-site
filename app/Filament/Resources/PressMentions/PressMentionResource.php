<?php

declare(strict_types=1);

namespace App\Filament\Resources\PressMentions;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\PressMentions\Pages\ManagePressMentions;
use App\Models\PressMention;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

/**
 * Раздел «Пресса о нас»: публикации сторонних изданий об ассоциации
 * и соревнованиях Carter 30 (ТЗ 3-го этапа, п. 9).
 *
 * Минимум для записи — заголовок, издание и ссылка на оригинал. Текст статьи
 * (перепечатка) необязателен: если его нет, страница публикации не заводится,
 * а карточка на сайте ведёт сразу на сайт издания.
 */
class PressMentionResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = PressMention::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Пресса о нас';

    protected static ?int $navigationSort = 9;

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
        return 'Публикация в прессе';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Пресса о нас';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Публикация')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок статьи')
                            ->placeholder('Как в издании')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', $state ? Str::slug($state) : '')),

                        TextInput::make('slug')
                            ->label('Slug (адрес страницы)')
                            ->helperText('Часть ссылки на странице публикации: /press/…')
                            ->placeholder('avtomaticheski-zapolnyaetsya')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('source_name')
                            ->label('Издание')
                            ->placeholder('Например: Yacht Russia')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('source_url')
                            ->label('Ссылка на статью')
                            ->helperText('Полный адрес оригинала — он выводится на сайте кнопкой «Читать в оригинале».')
                            ->placeholder('https://')
                            ->url()
                            ->required()
                            ->maxLength(2048),

                        DatePicker::make('published_at')
                            ->label('Дата выхода')
                            ->helperText('Дата публикации в издании.')
                            ->displayFormat('d.m.Y')
                            ->default(now()),

                        Toggle::make('is_published')
                            ->label('Опубликовано')
                            ->helperText('Неопубликованная запись не видна на сайте.')
                            ->default(false),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->helperText('Меньше — выше в списке. При равном значении сортировка по дате выхода.')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Section::make('Текст')
                    ->description('Краткое описание попадает в карточку списка, текст статьи — на её страницу. Без текста карточка ведёт сразу на сайт издания.')
                    ->schema([
                        Textarea::make('summary')
                            ->label('Краткое описание')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        RichEditor::make('content')
                            ->label('Текст статьи')
                            ->helperText('Перепечатка материала. Картинки добавляйте кнопкой «Прикрепить файлы»: при копировании из Word переносится только текст.')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('press/inline')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsMaxSize(5120)
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
                    ->label('Заголовок')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('source_name')
                    ->label('Издание')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Дата выхода')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Опубликовано')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->stackedOnMobile()
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Опубликовано'),
                TrashedFilter::make(),
            ])
            ->emptyStateHeading('Публикаций пока нет')
            ->emptyStateDescription('Добавьте статью — она появится в разделе «Пресса о нас» и на главной странице.')
            ->recordActions([
                Action::make('openSource')
                    ->label('Оригинал')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (PressMention $record): string => $record->source_url)
                    ->openUrlInNewTab(),
                EditAction::make()->modalHeading('Редактировать публикацию'),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Публикация удалена'),
                    ),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePressMentions::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
