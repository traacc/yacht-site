<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\SendEmailVerificationLinkAction;
use App\Enums\CreationSource;
use App\Enums\SportCategory;
use App\Mail\PasswordResetRequested;
use App\Mail\UserRegistered;
use App\Models\User;
use App\Rules\YandexCaptcha;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class LoginModal extends Component
{
    // Поля формы
    public string $name = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $patronymic = '';

    public string $birth_day = '';

    public string $birth_month = '';

    public string $birth_year = '';

    public string $sports_category = '';

    public string $phone = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $loginCaptchaToken = '';

    public string $registerCaptchaToken = '';

    // Данные для отправки credentials
    public string $selectedUserId = '';

    public string $senderPassword = '';

    public string $sendStatus = '';

    // Восстановление пароля
    public bool $resetLinkSent = false;

    public function login()
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            // 'loginCaptchaToken' => ['required', new YandexCaptcha()],
        ], attributes: [
            'loginCaptchaToken.required' => 'Вам необходимо пройти проверку на бота',
            'email' => 'email',
            'password' => 'пароль',
        ]);

        // Попытка авторизации. Всегда выдаём remember-куку: сессия живёт
        // ограниченное время, и без неё пользователей выбивает после
        // периода неактивности.
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], remember: true)) {
            session()->regenerate();

            // Полная перезагрузка страницы (без wire:navigate): после
            // session()->regenerate() меняется CSRF-токен, а navigate обновляет
            // только <body> и оставляет старый токен из @livewireScripts в <head>,
            // из-за чего все последующие Livewire-запросы падают с 419.
            return $this->redirect(route('home'));
        }

        // Если пароль не подошел, возвращаем ошибку в форму
        $this->addError('email', 'Неверный email или пароль.');
    }

    public function sendResetLink()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'phone' => ['required', 'string'],
        ], attributes: [
            'email' => 'email',
            'phone' => 'телефон',
        ]);

        // Отправляем заявку на восстановление пароля администраторам —
        // они свяжутся с пользователем по указанным контактам.
        $adminEmails = app(SettingsService::class)->adminNotificationEmails();
        if ($adminEmails !== []) {
            try {
                Mail::to($adminEmails)->send(
                    new PasswordResetRequested($this->email, $this->phone)
                );
            } catch (\Exception $e) {
                report($e);
            }
        }

        $this->resetLinkSent = true;
    }

    public function register()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                // ФИО должно содержать отчество: «Фамилия Имя Отчество» — минимум три слова.
                if (count(preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY)) < 3) {
                    $fail('Укажите ФИО полностью, включая отчество (Фамилия Имя Отчество).');
                }
            }],
            /*
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'patronymic'            => ['required', 'string', 'max:255'],
            */
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults(), 'same:password_confirmation'],
            'password_confirmation' => ['required'],
            'phone' => ['required', 'unique:users,phone'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'birth_year' => ['required', 'integer', 'min:1926', 'max:2016'],
            'sports_category' => ['nullable', Rule::enum(SportCategory::class)],
            // 'registerCaptchaToken' => ['required', new YandexCaptcha()],
        ], attributes: [
            'registerCaptchaToken.required' => 'Вам необходимо пройти проверку на бота',
            'name' => 'ФИО',
            /*
            'first_name'            => 'имя',
            'last_name'             => 'фамилия',
            'patronymic'            => 'отчество',
            */
            'email' => 'email',
            'phone' => 'телефон',
            'birth_day' => 'день рождения',
            'birth_month' => 'месяц рождения',
            'birth_year' => 'год рождения',
            'password' => 'пароль',
            'password_confirmation' => 'подтверждение пароля',
            'sports_category' => 'спортивный разряд',
        ], messages: [
            'email.unique' => 'Пользователь с таким email уже зарегистрирован',
            'phone.unique' => 'Пользователь с таким телефоном уже зарегистрирован',
        ]);

        // Проверка корректности даты (30 февраля и т.п.)
        if (! checkdate((int) $this->birth_month, (int) $this->birth_day, (int) $this->birth_year)) {
            $this->addError('birth_day', 'Некорректная дата рождения.');

            return;
        }

        // 1. Создаем пользователя
        $birthDate = sprintf('%04d-%02d-%02d', $this->birth_year, $this->birth_month, $this->birth_day);

        $user = User::create([
            'name' => $this->name,

            'first_name' => '',
            'last_name' => '',
            /*
            'patronymic'     => $this->patronymic,
            */
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'birth_date' => $birthDate,
            'sport_category' => $this->sports_category ?: null,
            // Пароль передаём без хеширования — каст 'hashed' в модели сделает это автоматически
            'password' => $this->password,
            'creation_source' => CreationSource::Registration,
        ]);

        // Уведомляем администраторов о регистрации нового пользователя.
        $adminEmails = app(SettingsService::class)->adminNotificationEmails();
        if ($adminEmails !== []) {
            try {
                Mail::to($adminEmails)->send(new UserRegistered($user));
            } catch (\Exception $e) {
                report($e);
            }
        }

        // Письмо для подтверждения e-mail (без него недоступна онлайн-оплата).
        // Сбой почты не должен ломать регистрацию — письмо можно запросить повторно.
        try {
            app(SendEmailVerificationLinkAction::class)->handle($user, throttle: false);
        } catch (\Exception $e) {
            report($e);
        }

        // 2. Автоматически входим в систему (с remember-кукой, см. login())
        Auth::login($user, remember: true);

        session()->regenerate();

        session()->flash('registered', true);

        // 3. Редирект на главную (полная перезагрузка, не wire:navigate —
        // см. комментарий в login() про CSRF-токен после regenerate)
        return $this->redirect(route('home'));
    }

    public function sendLoginCredentials()
    {
        $this->validate([
            'selectedUserId' => ['required', 'exists:users,id'],
            'senderPassword' => ['required'],
        ], attributes: [
            'selectedUserId' => 'пользователь',
            'senderPassword' => 'пароль',
        ]);

        $user = User::findOrFail($this->selectedUserId);

        // Проверяем пароль через getAuthPassword() — он возвращает сырое значение из БД
        if (! Hash::check($this->senderPassword, $user->getAuthPassword())) {
            $this->addError('senderPassword', 'Неверный пароль пользователя.');

            return;
        }

        // Входим под выбранным пользователем (с remember-кукой, см. login())
        Auth::login($user, remember: true);
        session()->regenerate();

        $this->selectedUserId = '';
        $this->senderPassword = '';

        // Полная перезагрузка, не wire:navigate (см. комментарий в login())
        return $this->redirect(route('home'));
    }

    public function getUsersProperty()
    {
        return User::query()
            ->whereNotNull('email')
            /*
            ->orderBy('last_name')
            ->orderBy('first_name')
            */
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function render()
    {
        return view('livewire.auth.login-modal');
    }
}
