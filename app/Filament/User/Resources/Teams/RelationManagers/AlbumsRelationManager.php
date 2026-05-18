<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Teams\RelationManagers;

use App\Models\Album;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;


class AlbumsRelationManager extends RelationManager
{
    protected static string $relationship = 'albums';

    protected static ?string $title = 'Галерея';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Название альбома')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Описание')
                    ->rows(3)
                    ->columnSpanFull(),

                FileUpload::make('cover_url')
                    ->label('Обложка альбома')
                    ->image()
                    ->directory('albums/covers')
                    ->columnSpanFull(),

                Repeater::make('media')
                    ->label('Фотографии')
                    ->relationship('media')
                    ->schema([
                        Hidden::make('type')
                            ->default('photo'),

                        FileUpload::make('url')
                            ->label('Фото')
                            ->image()
                            ->directory('albums/photos')
                            ->required(),

                        TextInput::make('original_filename')
                            ->label('Имя файла')
                            ->disabled()
                            ->dehydrated(false),

                        Hidden::make('sort_order')
                            ->default(0),
                    ])
                    ->orderColumn('sort_order')
                    ->collapsible()
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\ImageColumn::make('cover_url')
                    ->label('Обложка')
                    ->circular()
                    ->defaultImageUrl(fn (Album $record): ?string => $record->cover),

                Tables\Columns\TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Описание')
                    ->limit(60)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('media_count')
                    ->label('Фото')
                    ->counts('media')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добавить альбом'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
