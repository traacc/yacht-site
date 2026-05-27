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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
// ★ ЗАМЕНЕНО: FileUpload → SpatieMediaLibraryFileUpload
// Было: use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static string|BackedEnum|null $navigationIcon = 'gallery';
    

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
                    ->relationship('season', 'year')
                    ->searchable()
                    ->preload(),

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
                    ->multiple()
                    ->reorderable()
                    ->disk('public')
                    ->visibility('public')
                    ->maxFiles(200)
                    ->columnSpanFull(),

                // ★ ДОБАВЛЕНО: новое поле для загрузки видео.
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

                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0),

                // ★ УДАЛЕНО: is_published всегда true при создании (устанавливается в модели Gallery)
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
            ->columns([

                // ★ ДОБАВЛЕНО: превью обложки в таблице
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label('Обложка')
                    ->collection('cover')
                    ->conversion('thumb')                 // конверсия 150×150
                    ->circular()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('season.year')
                    ->label('Сезон')
                    ->sortable(),

                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                // ★ ДОБАВЛЕНО: количество фото и видео
                TextColumn::make('media_count')
                    ->label('Фото/Видео')
                    ->state(fn (Gallery $record): string => sprintf(
                        '%d фото / %d видео',
                        $record->getMedia('images')->count(),
                        $record->getMedia('videos')->count(),
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

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать галерею'),
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
