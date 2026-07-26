{{-- Баннер «Подтвердите e-mail» в личном кабинете --}}
<div>
    @if ($visible)
        <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="font-semibold">Подтвердите e-mail.</span>
                    Без подтверждения недоступна онлайн-оплата взносов и услуг.
                    Мы отправили письмо со ссылкой на <span class="font-semibold">{{ $email }}</span>.

                    @if ($sent)
                        <span class="mt-1 block text-green-700">Письмо отправлено повторно — проверьте почту, в том числе папку «Спам».</span>
                    @endif

                    @if ($error)
                        <span class="mt-1 block text-red-700">{{ $error }}</span>
                    @endif
                </div>

                @unless ($sent)
                    <button type="button" wire:click="resend" wire:loading.attr="disabled"
                            class="shrink-0 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="resend">Отправить письмо ещё раз</span>
                        <span wire:loading wire:target="resend">Отправляем…</span>
                    </button>
                @endunless
            </div>
        </div>
    @endif
</div>
