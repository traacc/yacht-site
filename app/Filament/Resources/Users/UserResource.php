<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    public static function getModelLabel(): string
    {
        return 'Пользователь'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Пользователи'; // Название во множественном числе
    }
    
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'user';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Имя пользователя')
                    ->placeholder('Имя пользователя')
                    ->required(),
                TextInput::make('first_name')
                    ->label('Имя')
                    ->placeholder('Имя')
                    ->required(),
                TextInput::make('last_name')
                    ->label('Фамилия')
                    ->placeholder('Фамилия')
                    ->required(),
                DatePicker::make('birth_date')
                    ->label('Дата рождения'),
                TextInput::make('sport_rank')
                    ->label('Спортивный разряд')
                    ->placeholder('Спортивный разряд'),
                TextInput::make('email')
                    ->label('Email')
                    ->placeholder('email@example.com')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at')
                    ->label('Email подтверждён'),
                TextInput::make('phone')
                    ->label('Телефон')
                    ->placeholder('+7 (999) 123-45-67')
                    ->tel(),
                DateTimePicker::make('phone_verified_at')
                    ->label('Телефон подтверждён'),
                TextInput::make('password')
                    ->label('Пароль')
                    ->placeholder('Пароль')
                    ->password()
                    ->required(),
                TextInput::make('photo_url')
                    ->label('URL фото')
                    ->placeholder('https://example.com/photo.jpg')
                    ->url(),
                Select::make('system_role')
                    ->label('Системная роль')
                    ->placeholder('Выберите роль')
                    ->options([
                        'user' => 'Пользователь',
                        'admin' => 'Администратор',
                        'judge' => 'Судья',
                        'secretary' => 'Секретарь',
                        'accountant' => 'Бухгалтер',
                    ])
                    ->default('user')
                    ->required(),
                Toggle::make('is_banned')
                    ->label('Забанен'),
                Textarea::make('ban_reason')
                    ->label('Причина бана')
                    ->placeholder('Причина бана')
                    ->columnSpanFull(),
                Textarea::make('ban_comment')
                    ->label('Комментарий к бану')
                    ->placeholder('Комментарий к бану')
                    ->columnSpanFull(),
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
                    ->label('Имя пользователя')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Фамилия')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Дата рождения')
                    ->date()
                    ->sortable(),
                TextColumn::make('sport_rank')
                    ->label('Спортивный разряд')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->label('Email подтверждён')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('phone_verified_at')
                    ->label('Телефон подтверждён')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('photo_url')
                    ->label('Фото (URL)')
                    ->searchable(),
                TextColumn::make('system_role')
                    ->label('Роль')
                    ->badge(),
                IconColumn::make('is_banned')
                    ->label('Забанен')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Удалено')
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
            'index' => ManageUsers::route('/'),
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
