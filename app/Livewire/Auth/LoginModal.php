<?php

namespace App\Livewire\Auth;

use App\Enums\SportCategory;
use App\Mail\SendLoginCredentials;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

use App\Rules\YandexCaptcha;

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
    public bool $remember = false;

    // Данные для отправки credentials
    public string $selectedUserId = '';
    public string $senderPassword = '';
    public string $sendStatus = '';

    // Восстановление пароля
    public bool $resetLinkSent = false;


    public function login()
    {
        $credentials = $this->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
            //'loginCaptchaToken' => ['required', new YandexCaptcha()],
        ], attributes: [
            'loginCaptchaToken.required' => 'Вам необходимо пройти проверку на бота',
            'email'    => 'email',
            'password' => 'пароль',
        ]);

        
        // Попытка авторизации
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            session()->regenerate();

            // Перенаправляем пользователя или обновляем страницу
            return $this->redirect(route('home'), navigate: true);
        }

        // Если пароль не подошел, возвращаем ошибку в форму
        $this->addError('email', 'Неверный email или пароль.');
    }


    public function sendResetLink()
    {
        $this->validate([
            'email' => ['required', 'email'],
        ], attributes: [
            'email' => 'email',
        ]);

        $status = PasswordBroker::sendResetLink(['email' => $this->email]);

        if ($status === PasswordBroker::RESET_LINK_SENT) {
            $this->resetLinkSent = true;

            return;
        }

        $this->addError('email', __($status));
    }


    public function register()
    {
        $this->validate([
            'name'                  => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (User::whereRaw('LOWER(name) = LOWER(?)', [$value])->exists()) {
                    $fail('Пользователь с таким ФИО уже зарегистрирован');
                }
            }],
            /*
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'patronymic'            => ['required', 'string', 'max:255'],
            */
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', Password::defaults(), 'same:password_confirmation'],
            'password_confirmation' => ['required'],
            'phone'                 => ['required', 'unique:users,phone'],
            'birth_day'             => ['required', 'integer', 'min:1', 'max:31'],
            'birth_month'           => ['required', 'integer', 'min:1', 'max:12'],
            'birth_year'            => ['required', 'integer', 'min:1926', 'max:2016'],
            'sports_category'       => ['nullable', Rule::enum(SportCategory::class)],
            //'registerCaptchaToken' => ['required', new YandexCaptcha()],
        ], attributes: [
            'registerCaptchaToken.required' => 'Вам необходимо пройти проверку на бота',
            'name'                  => 'ФИО',
            /*
            'first_name'            => 'имя',
            'last_name'             => 'фамилия',
            'patronymic'            => 'отчество',
            */
            'email'                 => 'email',
            'phone'                 => 'телефон',
            'birth_day'             => 'день рождения',
            'birth_month'           => 'месяц рождения',
            'birth_year'            => 'год рождения',
            'password'              => 'пароль',
            'password_confirmation' => 'подтверждение пароля',
            'sports_category'       => 'спортивный разряд',
        ], messages: [
            'email.unique' => 'Пользователь с таким email уже зарегистрирован',
            'phone.unique' => 'Пользователь с таким телефоном уже зарегистрирован',
        ]);

        // Проверка корректности даты (30 февраля и т.п.)
        if (!checkdate((int) $this->birth_month, (int) $this->birth_day, (int) $this->birth_year)) {
            $this->addError('birth_day', 'Некорректная дата рождения.');
            return;
        }


        // 1. Создаем пользователя
        $birthDate = sprintf('%04d-%02d-%02d', $this->birth_year, $this->birth_month, $this->birth_day);

        $user = User::create([
            'name'           => $this->name,
            
            'first_name'     => '',
            'last_name'      => '',
            /*
            'patronymic'     => $this->patronymic,
            */
            'email'          => $this->email,
            'phone'          => $this->phone ?: null,
            'birth_date'     => $birthDate,
            'sport_category' => $this->sports_category ?: null,
            // Пароль передаём без хеширования — каст 'hashed' в модели сделает это автоматически
            'password'       => $this->password,
        ]);

        // 2. Автоматически входим в систему
        Auth::login($user);

        session()->regenerate();

        session()->flash('registered', true);

        // 3. Редирект на главную или в личный кабинет
        return $this->redirect(route('home'), navigate: true);
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
        if (!Hash::check($this->senderPassword, $user->getAuthPassword())) {
            $this->addError('senderPassword', 'Неверный пароль пользователя.');
            return;
        }

        // Входим под выбранным пользователем
        Auth::login($user);
        session()->regenerate();

        $this->selectedUserId = '';
        $this->senderPassword = '';

        return $this->redirect(route('home'), navigate: true);
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
            ->get(['id','name', 'email']);
    }

    public function render()
    {
        return view('livewire.auth.login-modal');
    }
    
}