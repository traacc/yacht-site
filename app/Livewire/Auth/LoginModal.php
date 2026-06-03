<?php

namespace App\Livewire\Auth;

use App\Enums\SportCategory;
use App\Mail\SendLoginCredentials;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

use App\Rules\YandexCaptcha;

class LoginModal extends Component
{
    // Поля формы
    public string $first_name = '';
    public string $last_name = '';
    public string $patronymic = '';
    public string $birthday = '';
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
    public string $sendStatus = '';


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


    public function register()
    {
        $this->validate([
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'patronymic'            => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', Password::defaults(), 'same:password_confirmation'],
            'password_confirmation' => ['required'],
            'phone'                 => ['required'],
            'birthday'              => ['required', 'date', 'before:today', 'after:1925-12-31'],
            'sports_category'       => ['nullable', Rule::enum(SportCategory::class)],
            //'registerCaptchaToken' => ['required', new YandexCaptcha()],
        ], attributes: [
            'registerCaptchaToken.required' => 'Вам необходимо пройти проверку на бота',
            'first_name'            => 'имя',
            'last_name'             => 'фамилия',
            'patronymic'            => 'отчество',
            'email'                 => 'email',
            'phone'                 => 'телефон',
            'birthday'              => 'дата рождения',
            'password'              => 'пароль',
            'password_confirmation' => 'подтверждение пароля',
            'sports_category'       => 'спортивный разряд',
        ]);


        // 1. Создаем пользователя
        $user = User::create([
            'name'           => $this->first_name . ' ' . $this->last_name,
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'patronymic'      => $this->patronymic,
            'email'          => $this->email,
            'phone'          => $this->phone ?: null,
            'birth_date'     => $this->birthday ?: null,
            'sport_category' => $this->sports_category ?: null,
            'password'       => Hash::make($this->password),
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
        ], attributes: [
            'selectedUserId' => 'пользователь',
        ]);

        $user = User::findOrFail($this->selectedUserId);

        $email = $user->email;
        $password = \Illuminate\Support\Str::random(12);

        $user->password = Hash::make($password);
        $user->save();

        Mail::to($user)->send(new SendLoginCredentials($user, $email, $password));

        session()->flash('credentials_sent', 'Данные для входа отправлены на почту пользователю ' . $user->full_name);
        $this->selectedUserId = '';
    }

    public function getUsersProperty()
    {
        return User::query()
            ->whereNotNull('email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'last_name', 'first_name', 'patronymic', 'email']);
    }

    public function render()
    {
        return view('livewire.auth.login-modal');
    }
    
}