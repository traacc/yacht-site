{{--
    Витрина доски объявлений — одна на все доски (@see App\Enums\AdvertType).
    Фильтры серверные: форма шлёт GET, параметры переживают пагинацию
    благодаря withQueryString() в App\Services\AdvertBoard. Состав фильтров
    задаёт сама доска — лишние селекты не рендерятся.

    Ожидает: $type, $adverts, $categories, $cities, $regattas, $kindCounts, $filters.
--}}
@php
    $activeKind = $filters['kind'] ?? '';
@endphp
<x-public-layout
    :title="$type->pluralLabel()"
    :description="$type->metaDescription()">

<x-breadcrumbs_page :title="$type->pluralLabel()">
</x-breadcrumbs_page>

<x-hero-section
    :title="$type->pluralLabel()"
    :desc="$type->heroDescription()"
    bgImage="{{ asset($type->heroImage()) }}"
>

</x-hero-section>

<section class="py-10 px-4 sm:px-6 lg:px-8">
    <div class="container mx-auto">

        {{-- Разместить объявление --}}
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <p class="text-brand-gray font-medium">
                Всего объявлений: {{ $adverts->total() }}
            </p>

            @guest
                <button type="button"
                        @click.prevent="$dispatch('open-login-modal', { tab: 'register' })"
                        class="bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors md:text-lg text-sm font-semibold">
                    {{ $type->submitButtonLabel() }} →
                </button>
            @else
                <a href="{{ url('/user/adverts') }}"
                   class="bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors md:text-lg text-sm font-semibold text-center">
                    {{ $type->submitButtonLabel() }} →
                </a>
            @endguest
        </div>

        {{-- Предложения / запросы: только у досок с дуальностью --}}
        @if ($kindCounts !== [])
            <div class="flex flex-wrap gap-2 mb-6">
                @php($kindTabs = ['' => 'Все'] + collect($type->kinds())->mapWithKeys(fn ($kind) => [$kind->value => $type->kindLabel($kind)])->all())
                @foreach ($kindTabs as $value => $label)
                    <a href="{{ request()->fullUrlWithQuery(['kind' => $value ?: null, 'page' => null]) }}"
                       class="px-5 py-2 text-sm md:text-base font-semibold border transition-colors
                              {{ $activeKind === $value
                                  ? 'bg-[#2D92CE] text-white border-[#2D92CE]'
                                  : 'bg-white text-[#2D92CE] border-[#C6C6C6] hover:border-[#2D92CE]' }}">
                        {{ $label }}
                        @if ($value !== '')
                            <span class="opacity-70">({{ $kindCounts[$value] ?? 0 }})</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Фильтры --}}
        <form method="GET" action="{{ url()->current() }}" class="bg-[#F8F8F8] p-4 mb-8">
            {{-- Выбранный вид живёт в табах, но должен пережить отправку формы. --}}
            @if ($activeKind !== '')
                <input type="hidden" name="kind" value="{{ $activeKind }}">
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Поиск по объявлениям"
                       class="border border-[#C6C6C6] p-3 text-sm md:text-base w-full">

                @if ($categories->isNotEmpty())
                    <select name="category" class="border border-[#C6C6C6] p-3 text-sm md:text-base w-full">
                        <option value="">Все категории</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') === $category->id)>
                                {{ $category->title }}
                            </option>
                        @endforeach
                    </select>
                @endif

                @if ($cities !== [])
                    <select name="city" class="border border-[#C6C6C6] p-3 text-sm md:text-base w-full">
                        <option value="">Все города</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($type->usesPosition())
                    <select name="position" class="border border-[#C6C6C6] p-3 text-sm md:text-base w-full">
                        <option value="">Любая позиция</option>
                        @foreach (\App\Enums\AdvertPosition::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['position'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($type->usesSportCategory())
                    <select name="sport_category" class="border border-[#C6C6C6] p-3 text-sm md:text-base w-full">
                        <option value="">Любой разряд</option>
                        @foreach (\App\Enums\SportCategory::cases() as $case)
                            <option value="{{ $case->value }}" @selected(($filters['sport_category'] ?? '') === $case->value)>{{ $case->getLabel() }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($regattas->isNotEmpty())
                    <select name="regatta" class="border border-[#C6C6C6] p-3 text-sm md:text-base w-full">
                        <option value="">Все регаты</option>
                        @foreach ($regattas as $regatta)
                            <option value="{{ $regatta->id }}" @selected(($filters['regatta'] ?? '') === $regatta->id)>
                                {{ $regatta->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <select name="sort" class="border border-[#C6C6C6] p-3 text-sm md:text-base w-full">
                    @foreach (\App\Services\AdvertBoard::sortOptions() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="number" name="price_from" min="0" value="{{ $filters['price_from'] ?? '' }}"
                       placeholder="Цена от, ₽"
                       class="border border-[#C6C6C6] p-3 text-sm md:text-base w-full">

                <input type="number" name="price_to" min="0" value="{{ $filters['price_to'] ?? '' }}"
                       placeholder="Цена до, ₽"
                       class="border border-[#C6C6C6] p-3 text-sm md:text-base w-full">

                <button type="submit"
                        class="bg-[#2D92CE] text-white py-3 px-6 hover:bg-[#0074CC] transition-colors font-semibold">
                    Показать
                </button>

                <a href="{{ url()->current() }}"
                   class="border border-[#2D92CE] text-[#2D92CE] py-3 px-6 hover:bg-[#2D92CE] hover:text-white transition-colors font-semibold text-center">
                    Сбросить
                </a>
            </div>
        </form>

        {{-- Список --}}
        @if ($adverts->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ($adverts as $advert)
                    @include('partials.advert-card', ['advert' => $advert])
                @endforeach
            </div>

            @if ($adverts->hasPages())
                <div class="mt-10">
                    {{ $adverts->links() }}
                </div>
            @endif
        @else
            <div class="text-center text-brand-gray-light py-12">
                Объявлений не найдено. Попробуйте изменить параметры поиска.
            </div>
        @endif
    </div>
</section>

<x-feedback-section>

</x-feedback-section>
</x-public-layout>
