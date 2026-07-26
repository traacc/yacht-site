<?php

namespace App\Filament\User\Pages;

use App\Actions\Auth\SendEmailVerificationLinkAction;
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
use Illuminate\Database\Eloquent\Model;
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

    /** Новый e-mail, на который нужно отправить письмо после сохранения. */
    protected ?string $pendingVerificationEmail = null;

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

            return;
        }

        // Письмо отправляем только после коммита: parent::save() оборачивает
        // сохранение в транзакцию, а отправленное письмо не откатится.
        if ($this->pendingVerificationEmail === null) {
            return;
        }

        $this->pendingVerificationEmail = null;

        try {
            app(SendEmailVerificationLinkAction::class)->handle($this->getUser(), throttle: false);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Не удалось отправить письмо')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Подтвердите новый e-mail')
            ->body('Мы отправили письмо со ссылкой на новый адрес. Онлайн-оплата будет доступна после подтверждения.')
            ->success()
            ->send();
    }

    /**
     * При смене e-mail подтверждение сбрасывается — новый адрес нужно подтвердить заново.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $emailChanged = array_key_exists('email', $data)
            && $data['email'] !== $record->getAttributeValue('email');

        $record = parent::handleRecordUpdate($record, $data);

        if ($emailChanged && ! $record->hasTechnicalEmail()) {
            // email_verified_at не в $fillable — только forceFill;
            // saveQuietly, чтобы не запускать проверку дублей ФИО в хуке saving.
            $record->forceFill(['email_verified_at' => null])->saveQuietly();

            $this->pendingVerificationEmail = $record->email;
        }

        return $record;
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
