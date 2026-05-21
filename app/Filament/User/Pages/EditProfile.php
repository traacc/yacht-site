<?php

namespace App\Filament\User\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class EditProfile extends BaseEditProfile
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;
    protected static ?string $navigationLabel = 'Профиль';          // название вкладки
    protected static ?int $navigationSort = -1;

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Аккаунт'; // Or set a group name like 'Аккаунт'
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([

                        FileUpload::make('photo_url')
                            ->label('Изменить фотографию')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->directory('avatars')
                            ->columnSpanFull()
                            ->visibility('public')
                            ->extraFieldWrapperAttributes(['class' => 'photo_wrapper']),

                        TextInput::make('name')
                            ->label('Отображаемое имя')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('first_name')
                            ->label('Имя')
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Фамилия')
                            ->maxLength(255),
                        DatePicker::make('birth_date')
                            ->label('Дата рождения')
                            ->native(false),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(255),
                        Select::make('sport_category')
                            ->label('Спортивный разряд')
                            ->options([
                                'mc' => 'МС',
                            ]),
                    ])->columns(2),

                Section::make('Контакты')
                    ->schema([

                    ])->columns(2),


            ]);
    }
}
