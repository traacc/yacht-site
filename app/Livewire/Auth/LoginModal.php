<?php

namespace App\Livewire\Auth;

use App\Enums\SportCategory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class LoginModal extends Component
{
    // Поля формы
    public string $first_name = '';
    public string $last_name = '';
    public string $birthday = '';
    public string $sports_category = '';
    public string $phone = '';


    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $remember = false;


    public function login()
    {
        $credentials = $this->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ], attributes: [
            'email'    => 'email',
            'password' => 'password',
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
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', Password::defaults(), 'same:password_confirmation'],
            'password_confirmation' => ['required'],
            'sports_category'       => ['nullable', Rule::enum(SportCategory::class)],
        ], attributes: [
            'first_name'            => 'имя',
            'last_name'             => 'фамилия',
            'email'                 => 'email',
            'password'              => 'пароль',
            'password_confirmation' => 'подтверждение пароля',
            'sports_category'       => 'спортивный разряд',
        ]);


        // 1. Создаем пользователя
        $user = User::create([
            'name'           => $this->first_name . ' ' . $this->last_name,
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'email'          => $this->email,
            'phone'          => $this->phone ?: null,
            'birth_date'     => $this->birthday ?: null,
            'sport_category' => $this->sports_category ?: null,
            'password'       => Hash::make($this->password),
        ]);

        // 2. Автоматически входим в систему
        Auth::login($user);

        session()->regenerate();

        // 3. Редирект на главную или в личный кабинет
        return $this->redirect(route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login-modal');
    }
    
}