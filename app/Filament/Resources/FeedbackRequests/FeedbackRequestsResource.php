<?php

namespace App\Filament\Resources\FeedbackRequests;

use App\Filament\Resources\FeedbackRequests\Pages\ManageFeedbackRequests;
use App\Models\FeedbackRequests;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeedbackRequestsResource extends Resource
{
    protected static ?string $model = FeedbackRequests::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


    public static function getModelLabel(): string
    {
        return 'Заявка на обратную связь'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на обратную связь'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Имя')
                    ->placeholder('Введите имя')
                    ->required(),
                TextInput::make('phone')
                    ->label('Телефон')
                    ->tel()
                    ->placeholder('+7 (999) 123-45-67')
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->placeholder('user@example.com')
                    ->email(),
                Textarea::make('message')
                    ->label('Сообщение')
                    ->placeholder('Введите текст сообщения')
                    ->columnSpanFull(),
                TextInput::make('source')
                    ->label('Источник')
                    ->placeholder('Откуда пришла заявка'),
                Select::make('user_id')
                    ->label('Пользователь')
                    ->relationship('user', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('source')
                    ->label('Источник')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFeedbackRequests::route('/'),
        ];
    }
}
