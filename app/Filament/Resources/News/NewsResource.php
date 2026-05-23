<?php

namespace App\Filament\Resources\News;

use App\Filament\Resources\News\Pages\ManageNews;
use App\Models\News;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static string|BackedEnum|null $navigationIcon = 'news';


    public static function getModelLabel(): string
    {
        return 'Новость'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Новости'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Заголовок')
                    ->placeholder('Введите заголовок новости')
                    ->required(),
                Textarea::make('content')
                    ->label('Содержание')
                    ->placeholder('Введите текст новости')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('cover_image_url')
                    ->label('Обложка')
                    ->image()
                    ->disk('public')
                    ->directory('news/covers')
                    ->visibility('public'),

                Repeater::make('albums')
                    ->label('Галерея (альбомы)')
                    ->relationship('albums')
                    ->addActionLabel('Добавить альбом')
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->collapsible()
                    ->schema([
                        TextInput::make('title')
                            ->label('Название альбома')
                            ->required()
                            ->columnSpanFull(),
                        Repeater::make('media')
                            ->label('Фотографии')
                            ->relationship('media')
                            ->addActionLabel('Добавить фото')
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->schema([
                                Hidden::make('type')->default('photo'),
                                FileUpload::make('url')
                                    ->label('Фото')
                                    ->image()
                                    ->directory('albums/photos')
                                    ->disk('public')
                                    ->required(),
                                Hidden::make('sort_order')->default(0),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable(),
                TextColumn::make('created_at')
                ->label('Дата')
                    ->dateTime()
                    ->sortable(),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
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
            'index' => ManageNews::route('/'),
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
