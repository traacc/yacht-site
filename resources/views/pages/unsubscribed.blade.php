<x-public-layout title="Вы отписались от рассылки">
    <main class="main">
        <section class="md:py-12 py-4">
            <div class="container mx-auto">
                <div class="max-w-xl mx-auto bg-white rounded-2xl shadow p-8 text-center">
                    <h2 class="section-title a-font text-4xl mb-4">Вы отписались</h2>

                    <p class="text-gray-600 mb-2">
                        Уведомления категории «{{ $category->getLabel() }}»
                        @if ($channel)
                            больше не будут приходить на {{ mb_strtolower($channel->getLabel()) }}.
                        @else
                            отключены по всем каналам.
                        @endif
                    </p>

                    <p class="text-gray-600 mb-6">
                        Остальные уведомления продолжат приходить. Вернуть подписку или настроить
                        каналы можно в личном кабинете в любой момент.
                    </p>

                    <a href="{{ route('filament.user.pages.notification-settings') }}" class="inline-block bg-blue-600 text-white rounded-lg px-6 py-3 hover:bg-blue-700 transition">
                        Настроить уведомления
                    </a>
                </div>
            </div>
        </section>
    </main>
</x-public-layout>
