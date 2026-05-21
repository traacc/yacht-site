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
                Select::make('author_id')
                    ->label('Автор')
                    ->relationship('author', 'name'),
                Select::make('type')
                    ->label('Тип')
                    ->options(['manual' => 'Ручная', 'external' => 'Внешняя'])
                    ->default('manual')
                    ->required(),
                TextInput::make('title')
                    ->label('Заголовок')
                    ->placeholder('Введите заголовок новости')
                    ->required(),
                Textarea::make('content')
                    ->label('Содержание')
                    ->placeholder('Введите текст новости')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('external_url')
                    ->label('Внешняя ссылка')
                    ->placeholder('https://example.com')
                    ->url(),
                FileUpload::make('cover_image_url')
                    ->label('Обложка')
                    ->image(),
                Toggle::make('published_to_tg')
                    ->label('Опубликовано в Telegram'),
                DateTimePicker::make('published_at')
                    ->label('Дата публикации')->maxDate(now()->addMonths(3)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('author.name')
                    ->label('Автор')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge(),
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable(),
                TextColumn::make('external_url')
                    ->label('Внешняя ссылка')
                    ->searchable(),
                ImageColumn::make('cover_image_url')
                    ->label('Обложка'),
                IconColumn::make('published_to_tg')
                    ->label('Telegram')
                    ->boolean(),
                TextColumn::make('published_at')
                    ->label('Опубликовано')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
