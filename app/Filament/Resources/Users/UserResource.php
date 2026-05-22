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
                FileUpload::make('photo_url')
                    ->label('Изменить фотографию')
                    ->avatar()
                    ->image()
                    ->imageEditor()
                    ->directory('avatars')
                    ->columnSpanFull()
                    ->visibility('public')
                    ->extraFieldWrapperAttributes(['class' => 'photo_wrapper']),
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
                TextInput::make('email')
                    ->label('Email')
                    ->placeholder('email@example.com')
                    ->email()
                    ->required(),
                
                TextInput::make('phone')
                    ->label('Телефон')
                    ->placeholder('+7 (999) 123-45-67')
                    ->tel(),
                TextInput::make('sport_category')
                    ->label('Спортивный разряд')
                    ->placeholder('Спортивный разряд'),




                Select::make('system_role')
                    ->label('Системная роль')
                    ->placeholder('Выберите роль')
                    ->options([
                        'user' => 'Пользователь',
                        'admin' => 'Администратор',
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
                TextColumn::make('name')
                    ->label('Имя пользователя')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Дата рождения')
                    ->date()
                    ->sortable(),
                TextColumn::make('system_role')
                    ->label('Роль')
                    ->badge(),

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
