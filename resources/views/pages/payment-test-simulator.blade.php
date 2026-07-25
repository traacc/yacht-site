<x-public-layout title="Тестовая оплата">
    <main class="main">
        <section class="md:py-12 py-4">
            <div class="container mx-auto">
                <div class="max-w-xl mx-auto bg-white rounded-2xl shadow p-8">
                    <p class="text-sm uppercase tracking-wide text-amber-600 font-semibold mb-2">Тестовый провайдер</p>
                    <h2 class="section-title a-font text-4xl mb-6">Страница оплаты</h2>

                    <dl class="mb-8 space-y-2">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Назначение</dt>
                            <dd class="text-right">{{ $transaction->description }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Сумма</dt>
                            <dd class="text-2xl font-semibold">{{ number_format((float) $transaction->amount, 2, ',', ' ') }} ₽</dd>
                        </div>
                    </dl>

                    <form method="POST" action="{{ $confirmUrl }}" class="flex flex-col sm:flex-row gap-4">
                        @csrf
                        <button type="submit" name="outcome" value="success"
                            class="flex-1 bg-green-600 text-white rounded-lg px-6 py-3 hover:bg-green-700 transition">
                            Оплатить
                        </button>
                        <button type="submit" name="outcome" value="cancel"
                            class="flex-1 bg-gray-200 text-gray-800 rounded-lg px-6 py-3 hover:bg-gray-300 transition">
                            Отказаться
                        </button>
                    </form>

                    <p class="text-xs text-gray-400 mt-6">
                        Это симулятор: реальное списание средств не происходит.
                    </p>
                </div>
            </div>
        </section>
    </main>
</x-public-layout>
