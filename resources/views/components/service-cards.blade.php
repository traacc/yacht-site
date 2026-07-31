@props([
    // Какие подразделы показать. По умолчанию — все с готовой страницей.
    'services' => null,
    // Текущий подраздел исключается из списка («другие услуги»).
    'current' => null,
])

{{--
    Сетка карточек подразделов «Услуг».

    Один источник для хаба и для блока «другие услуги» внизу лендингов: список
    подразделов задаёт ServiceType, поэтому новый подраздел появляется везде
    сразу после того, как у него появится маршрут.
--}}
@php
    $items = collect($services ?? \App\Enums\ServiceType::published())
        ->when($current !== null, fn ($list) => $list->reject(fn ($service) => $service === $current))
        ->values();
@endphp

@if ($items->isNotEmpty())
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($items as $service)
            <a href="{{ $service->url() }}"
               class="block border border-[#C6C6C6] p-6 hover:border-[#2D92CE] transition-colors group">
                <h3 class="a-font text-xl md:text-2xl mb-3 text-[#2E325C] group-hover:text-[#2D92CE] transition-colors">
                    {{ $service->label() }}
                </h3>
                <p class="text-brand-gray-light text-sm md:text-base">
                    {{ $service->shortDescription() }}
                </p>
                <span class="inline-block mt-4 text-[#2D92CE] font-semibold text-sm">Подробнее →</span>
            </a>
        @endforeach
    </div>
@endif
