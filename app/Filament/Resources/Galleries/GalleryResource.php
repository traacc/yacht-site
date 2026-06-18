<?php

namespace App\Filament\Resources\Galleries;

use App\Filament\Resources\Galleries\Pages\ManageGalleries;
use App\Models\Gallery;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
// ★ ЗАМЕНЕНО: FileUpload → SpatieMediaLibraryFileUpload
// Было: use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
// ★ ДОБАВЛЕНО: SpatieMediaLibraryImageColumn для отображения обложки в таблице
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\IconColumn;
// ↓↓↓ УДАЛЕНО: ImageColumn — заменён на SpatieMediaLibraryImageColumn
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GalleryResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = Gallery::class;

    protected static string|BackedEnum|null $navigationIcon = 'gallery';
    
    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return 'Галерея';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Галереи';
    }

    /**
     * ★ ИЗМЕНЕНИЯ в форме:
     *   – FileUpload::make('cover_path') → SpatieMediaLibraryFileUpload::make('cover')
     *     с ->collection('cover'). Spatie сам управляет путями хранения.
     *   – FileUpload::make('images') → SpatieMediaLibraryFileUpload::make('images')
     *     с ->collection('images'). Spatie сам управляет путями хранения.
     *   – ★ ДОБАВЛЕНО: SpatieMediaLibraryFileUpload::make('videos')
     *     для загрузки видеофайлов в коллекцию 'videos'.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->label('Сезон')
                    ->relationship('season', 'year',
                    modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('year'),)
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('year')
                            ->label('Год')
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2099),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Дата начала сезона')
                            ->displayFormat('d.m.Y')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Дата окончания сезона')
                            ->displayFormat('d.m.Y')
                            ->required(),
                    ])
                    ->createOptionUsing(fn (array $data): string => \App\Models\Season::create($data)->id),

                Select::make('regatta_id')
                    ->label('Регата')
                    ->relationship('regatta', 'name')
                    ->searchable()
                    ->preload(),

                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255),

                TextInput::make('water_area')
                    ->label('Акватория')
                    ->maxLength(255),

                DatePicker::make('date')
                    ->label('Дата')
                    ->displayFormat('d.m.Y')
                    ->minDate(now()->subYears(100))
                    ->maxDate(now()->addYears(100)),

                // ★ ЗАМЕНЕНО: было FileUpload::make('cover_path')->directory('gallery/covers')
                //   теперь SpatieMediaLibraryFileUpload с коллекцией 'cover'.
                SpatieMediaLibraryFileUpload::make('cover')
                    ->label('Обложка')
                    ->collection('cover')                 // коллекция из registerMediaCollections()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->imageEditor()
                    ->maxSize(10240)                      // 10 МБ на файл
                    ->validationMessages([
                        'max' => 'Файл слишком большой. Максимальный размер — 10 МБ.',
                    ])
                    ->disk('public')
                    ->visibility('public')
                    ->columnSpanFull(),

                // ★ ЗАМЕНЕНО: было FileUpload::make('images')->directory('gallery/photos')
                //   теперь SpatieMediaLibraryFileUpload с коллекцией 'images'.
                SpatieMediaLibraryFileUpload::make('images')
                    ->label('Фотографии галереи')
                    ->collection('images')                // коллекция из registerMediaCollections()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->panelLayout('grid')
                    ->multiple()
                    ->reorderable()
                    ->deletable(true)
                    ->maxSize(10240)                      // 10 МБ на файл
                    ->validationMessages([
                        'max' => 'Файл слишком большой. Максимальный размер — 10 МБ.',
                    ])
                    ->disk('public')
                    ->visibility('public')
                    ->maxFiles(500)
                    ->columnSpanFull(),

                // ★ ДОБАВЛЕНО: новое поле для загрузки видео.
                /*
                SpatieMediaLibraryFileUpload::make('videos')
                    ->label('Видео')
                    ->collection('videos')                // коллекция из registerMediaCollections()
                    ->multiple()
                    ->reorderable()
                    ->acceptedFileTypes([
                        'video/mp4',
                        'video/webm',
                        'video/ogg',
                        'video/quicktime',
                        'video/x-msvideo',
                    ])
                    ->disk('public')
                    ->visibility('public')
                    ->maxFiles(50)                        // разумное ограничение на видео
                    ->columnSpanFull(),

                */

                Forms\Components\Repeater::make('videoLinks')
                    ->label('Ссылки на видео')
                    ->relationship('videoLinks')
                    ->schema([
                        Textarea::make('url')
                            ->label('Блок с видео')
                            ->required()
                            ->maxLength(4096)
                            ->placeholder('Вставтье блок с видео'),
                    ])
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['url'] ?? null)
                    ->addActionLabel('Добавить ссылку')
                    ->columnSpanFull()
                    ->defaultItems(0),

                /*
                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0),
                */

                Toggle::make('is_published')
                    ->label('Опубликовано')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * ★ ИЗМЕНЕНИЯ в таблице:
     *   – ★ ДОБАВЛЕНО: SpatieMediaLibraryImageColumn для обложки (коллекция 'cover').
     *   – ★ ДОБАВЛЕНО: счетчики медиафайлов (photo_count, video_count).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            // Подсвечиваем красным неопубликованные галереи
            ->recordClasses(fn (Gallery $record): ?string => $record->is_published
                ? null
                : 'gallery-unpublished-row')
            ->columns([

                // ★ ДОБАВЛЕНО: превью обложки в таблице
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label('Обложка')
                    ->collection('cover')
                    ->conversion('thumb')                 // конверсия 150×150
                    ->circular()
                    ->toggleable(),

                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('season.year')
                    ->label('Сезон')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Опубликовано')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),


                // ★ ДОБАВЛЕНО: количество фото и видео
                TextColumn::make('media_count')
                    ->label('Фото/Ссылки')
                    ->state(fn (Gallery $record): string => sprintf(
                        '%d фото / %d ссылок',
                        $record->getMedia('images')->count(),
                        //$record->getMedia('videos')->count(),
                        $record->videoLinks()->count(),
                    ))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('season_id')
                    ->label('Сезон')
                    ->relationship('season', 'year'),

                SelectFilter::make('regatta_id')
                    ->label('Регата')
                    ->relationship('regatta', 'name'),

                TernaryFilter::make('is_published')
                    ->label('Опубликовано'),

                TrashedFilter::make(),
            ])
            // Открываем редактирование по клику на строку, но саму кнопку прячем
            ->recordAction(EditAction::class)
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Редактировать галерею'),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGalleries::route('/'),
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
