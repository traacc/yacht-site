<?php

namespace App\Filament\User\Pages;

use App\Enums\SportCategory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class EditProfile extends BaseEditProfile
{
    protected static string|BackedEnum|null $navigationIcon = 'profile';

    protected static ?string $navigationLabel = 'Профиль';          // название вкладки

    protected static ?int $navigationSort = -1;

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Аккаунт'; // Or set a group name like 'Аккаунт'
    }

    public function save(): void
    {
        try {
            parent::save();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Не удалось сохранить изменения')
                ->body($exception->validator->errors()->first())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('changePassword')
                ->label('Сменить пароль')
                ->icon('heroicon-o-lock-closed')
                ->color('white')
                ->form([
                    TextInput::make('current_password')
                        ->label('Текущий пароль')
                        ->password()
                        ->revealable()
                        ->required()
                        ->currentPassword(),
                    TextInput::make('password')
                        ->label('Новый пароль')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(Password::default())
                        ->same('password_confirmation'),
                    TextInput::make('password_confirmation')
                        ->label('Подтверждение пароля')
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    auth()->user()->update([
                        'password' => Hash::make($data['password']),
                    ]);

                    Notification::make()
                        ->title('Пароль успешно изменён')
                        ->success()
                        ->send();
                }),
            $this->getSaveFormAction(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                FileUpload::make('photo_url')
                    ->label('Изменить фотографию')
                    ->avatar()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'])
                    ->imageEditor()
                    ->disk('public')
                    ->directory('avatars')
                    ->columnSpanFull()
                    ->visibility('public')
                    ->imageEditor()
                    ->imageEditorViewportWidth(2000)
                    ->imageEditorViewportHeight(2000)
                    ->imageEditorAspectRatios([
                        '1:1',
                        null,
                    ])
                    ->extraFieldWrapperAttributes(['class' => 'photo_wrapper']),

                TextInput::make('name')
                    ->label('ФИО')
                    ->columnSpanFull()
                    ->maxLength(255),
                /*
                        TextInput::make('first_name')
                            ->label('Имя')
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Фамилия')
                            ->maxLength(255),
                        TextInput::make('patronymic')
                            ->label('Отчество')
                            ->maxLength(255),
                        */
                DatePicker::make('birth_date')
                    ->label('Дата рождения')
                    ->minDate(now()->subYears(100))
                    ->maxDate(now()->addYears(100))
                    ->displayFormat('d.m.Y')
                    ->native(false),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')
                    ->label('Телефон')
                    ->telRegex('/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/')
                    ->mask('+7 (999) 999-99-99')
                    ->placeholder('+7 (___) ___-__-__')
                    ->maxLength(255),
                Select::make('sport_category')
                    ->label('Спортивный разряд')
                    ->options(SportCategory::class),

                Textarea::make('about')
                    ->label('О себе')
                    ->placeholder('О себе')
                    ->rows(4)
                    ->maxLength(2000)
                    ->columnSpanFull(),

            ])->columns(2)->extraAttributes(['class' => 'profile_user_block']);
    }
}
