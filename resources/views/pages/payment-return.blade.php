@php
    use App\Enums\PaymentTransactionStatus;

    $status = $transaction->status;
    $isPending = $status === PaymentTransactionStatus::Pending;
@endphp
<x-public-layout title="Результат оплаты">
    @if ($isPending)
        {{-- Ждём вебхук провайдера: автообновление страницы --}}
        <meta http-equiv="refresh" content="5">
    @endif

    <main class="main">
        <section class="md:py-12 py-4">
            <div class="container mx-auto">
                <div class="max-w-xl mx-auto bg-white rounded-2xl shadow p-8 text-center">
                    @if ($status === PaymentTransactionStatus::Succeeded)
                        <h2 class="section-title a-font text-4xl mb-4">Оплата прошла успешно</h2>
                        <p class="text-gray-600 mb-2">{{ $transaction->description }}</p>
                        <p class="text-2xl font-semibold mb-6">{{ number_format((float) $transaction->amount, 2, ',', ' ') }} ₽</p>
                        <p class="text-gray-600 mb-6">Мы отправили подтверждение на вашу электронную почту.</p>
                    @elseif ($isPending)
                        <h2 class="section-title a-font text-4xl mb-4">Ожидаем подтверждение оплаты</h2>
                        <p class="text-gray-600 mb-6">
                            Платёж обрабатывается. Страница обновится автоматически —
                            это может занять до нескольких минут.
                        </p>
                    @else
                        <h2 class="section-title a-font text-4xl mb-4">Оплата не завершена</h2>
                        <p class="text-gray-600 mb-6">
                            {{ $transaction->failure_reason ?? 'Платёж был отменён или завершился ошибкой.' }}
                            Вы можете повторить попытку из личного кабинета.
                        </p>
                    @endif

                    <a href="{{ route('dashboard') }}" class="inline-block bg-blue-600 text-white rounded-lg px-6 py-3 hover:bg-blue-700 transition">
                        Перейти в личный кабинет
                    </a>
                </div>
            </div>
        </section>
    </main>
</x-public-layout>
