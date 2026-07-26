<?php

use App\Actions\Auth\SendEmailVerificationLinkAction;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;

layout('layouts.guest');

state(['sent' => false]);

$sendVerification = function (SendEmailVerificationLinkAction $action) {
    if (Auth::user()->hasVerifiedEmail()) {
        $this->redirectIntended(default: route('filament.user.home', absolute: false));

        return;
    }

    $this->sent = false;

    try {
        $action->handle(Auth::user());
    } catch (ValidationException $e) {
        $this->addError('email', collect($e->errors())->flatten()->first());

        return;
    }

    $this->sent = true;
};

$logout = function (Logout $logout) {
    $logout();

    $this->redirect('/', navigate: true);
};

?>

<div>
    <h1 class="text-2xl a-font text-[#2E325C] mb-4">Подтвердите e-mail</h1>

    <div class="mb-4 text-sm text-gray-600">
        Мы отправили письмо со ссылкой на <span class="font-medium text-[#2E325C]">{{ auth()->user()->email }}</span>.
        Перейдите по ссылке из письма — после этого станет доступна онлайн-оплата взносов и услуг.
    </div>

    @if ($sent)
        <div class="mb-4 text-sm text-green-600">
            Письмо отправлено повторно. Проверьте почту, в том числе папку «Спам».
        </div>
    @endif

    @error('email')
        <div class="mb-4 text-xs text-red-600">{{ $message }}</div>
    @enderror

    <button type="button" wire:click="sendVerification" wire:loading.attr="disabled"
            class="w-full bg-[#2D92CE] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90 disabled:opacity-60">
        <span wire:loading.remove wire:target="sendVerification">Отправить письмо ещё раз</span>
        <span wire:loading wire:target="sendVerification">Отправляем…</span>
    </button>

    <div class="mt-4 flex items-center justify-between text-sm">
        <a href="{{ route('filament.user.home') }}" class="text-[#2D92CE] hover:underline">
            Изменить e-mail в профиле
        </a>

        <button wire:click="logout" type="button" class="text-gray-600 hover:text-gray-900 underline">
            Выйти
        </button>
    </div>
</div>
