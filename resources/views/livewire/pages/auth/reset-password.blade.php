<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state('token')->locked();

state([
    'email' => fn () => request()->string('email')->value(),
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'token' => ['required'],
    'email' => ['required', 'string', 'email'],
    'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
]);

$resetPassword = function () {
    $this->validate(attributes: [
        'email' => 'email',
        'password' => 'пароль',
        'password_confirmation' => 'подтверждение пароля',
    ]);

    $resetUser = null;

    // Пытаемся сбросить пароль. При успехе обновляем пароль пользователя в БД.
    $status = Password::reset(
        $this->only('email', 'password', 'password_confirmation', 'token'),
        function ($user) use (&$resetUser) {
            $user->forceFill([
                'password' => Hash::make($this->password),
                'remember_token' => Str::random(60),
            ])->save();

            $resetUser = $user;

            event(new PasswordReset($user));
        }
    );

    // При ошибке показываем сообщение, иначе входим под пользователем и
    // отправляем его на главную страницу.
    if ($status != Password::PASSWORD_RESET) {
        $this->addError('email', __($status));

        return;
    }

    Auth::login($resetUser);
    session()->regenerate();

    session()->flash('status', __($status));

    $this->redirectRoute('home', navigate: true);
};

?>

<div>
    <h1 class="text-2xl a-font text-[#2E325C] mb-4">Восстановление пароля</h1>

    <form wire:submit="resetPassword" class="space-y-4">
        <!-- Email -->
        <div>
            <label for="email" class="block text-sm text-brand-gray-light">Email</label>
            <input wire:model="email" id="email" type="email" name="email" required autocomplete="username"
                   class="mt-1 block w-full border-0 border-b border-[#EAEAEA] sm:text-sm @error('email') border-red-300 @enderror">
            @error('email') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
        </div>

        <!-- Новый пароль -->
        <div x-data="{ show: false }" class="relative">
            <label for="password" class="block text-sm text-brand-gray-light">Новый пароль</label>
            <input :type="show ? 'text' : 'password'" wire:model="password" id="password" name="password" required autocomplete="new-password"
                   class="mt-1 block w-full border-0 border-b border-[#EAEAEA] sm:text-sm pr-8 @error('password') border-red-300 @enderror">
            <button type="button" @click="show = !show"
                    class="absolute right-2 bottom-2 text-gray-400 hover:text-gray-600 focus:outline-none">
                <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
            </button>
            @error('password') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
        </div>

        <!-- Подтверждение пароля -->
        <div x-data="{ show: false }" class="relative">
            <label for="password_confirmation" class="block text-sm text-brand-gray-light">Подтвердите пароль</label>
            <input :type="show ? 'text' : 'password'" wire:model="password_confirmation" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                   class="mt-1 block w-full border-0 border-b border-[#EAEAEA] sm:text-sm pr-8">
            <button type="button" @click="show = !show"
                    class="absolute right-2 bottom-2 text-gray-400 hover:text-gray-600 focus:outline-none">
                <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
            </button>
        </div>

        <button type="submit"
                wire:loading.attr="disabled"
                class="inline-flex w-full justify-center bg-[#2D92CE] px-3 py-2 text-sm font-semibold text-white shadow-xs focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">
            <span wire:loading.remove>Сохранить пароль</span>
            <span wire:loading>Сохраняем...</span>
        </button>
    </form>
</div>
