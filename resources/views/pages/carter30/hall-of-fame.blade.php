<x-public-layout title="Зал славы класса Carter 30" description="Лидеры сезонов класса Carter 30: топ-5 командного и топ-3 личного рейтинга за каждый сезон">
<x-breadcrumbs_page title="Зал славы">
</x-breadcrumbs_page>
<x-hero-section title="Зал славы"
desc="Лучшие команды и яхтсмены класса Carter 30 по итогам каждого сезона."
bgImage="{{ asset('images/bg/results.webp') }}"
>

</x-hero-section>

@php
    $cup = file_get_contents(public_path('images/icons/cup.svg'));
    $cupColors = [1 => 'text-[#C2A36B]', 2 => 'text-[#9FA6AD]', 3 => 'text-[#B56A3A]'];
    $blocks = [
        'teams' => ['title' => 'Командный рейтинг', 'top' => \App\Services\HallOfFame::TEAM_PLACES, 'event' => 'open-team-card', 'key' => 'teamId'],
        'personal' => ['title' => 'Личный рейтинг', 'top' => \App\Services\HallOfFame::PERSONAL_PLACES, 'event' => 'open-user-card', 'key' => 'userId'],
    ];
@endphp

<section class="py-10 px-4 sm:px-6 lg:px-8">
    <div class="container mx-auto">
        @if ($seasons->isNotEmpty())
            <div class="grid grid-cols-1 gap-6">
                @foreach ($seasons as $season)
                    <article class="bg-[#F8F8F8] p-4 md:p-6 flex flex-col">
                        <header class="flex flex-wrap items-center justify-between gap-3 mb-6">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="a-font text-3xl text-[#2E325C]">Сезон {{ $season['year'] }}</h2>
                                @if ($season['in_progress'])
                                    <span class="text-xs font-semibold text-[#2D92CE] bg-white px-2 py-1">Сезон продолжается</span>
                                @endif
                            </div>
                            <a href="{{ route('ratings', ['season' => $season['year']]) }}" class="text-[#2D92CE] font-semibold text-sm">Полный рейтинг →</a>
                        </header>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-10">
                            @foreach ($blocks as $key => $block)
                                <div>
                                    <h3 class="text-[#2E325C] text-lg font-semibold mb-3">
                                        {{ $block['title'] }}
                                        <span class="text-brand-gray-light text-sm font-medium">· топ-{{ $block['top'] }}</span>
                                    </h3>

                                    @if ($season[$key] !== [])
                                        <ol class="divide-y divide-[#EAEAEA] bg-white">
                                            @foreach ($season[$key] as $row)
                                                <li class="flex gap-3 px-3 py-2">
                                                    <div class="flex items-center gap-1 w-10 shrink-0 self-start pt-1 font-bold text-[#2E325C]">
                                                        <span class="{{ $cupColors[$row['place']] ?? 'opacity-0' }}">{!! $cup !!}</span>
                                                        <span>{{ $row['place'] }}</span>
                                                    </div>
                                                    <ul class="flex-1 min-w-0 space-y-1">
                                                        @foreach ($row['entries'] as $entry)
                                                            <li class="flex items-start gap-2 min-w-0">
                                                                <img src="{{ $entry['image'] ?? asset('images/icons/avatar-default.svg') }}" alt=""
                                                                    class="w-7 h-7 rounded-full object-cover shrink-0">
                                                                @if ($entry['id'])
                                                                    <button type="button"
                                                                        onclick="Livewire.dispatch('{{ $block['event'] }}', { {{ $block['key'] }}: '{{ $entry['id'] }}' })"
                                                                        class="text-left text-[#2E325C] hover:text-[#2D92CE] font-medium leading-7 break-words cursor-pointer">{{ $entry['name'] }}</button>
                                                                @else
                                                                    <span class="text-[#2E325C] font-medium leading-7 break-words">{{ $entry['name'] }}</span>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                    <div class="shrink-0 self-start pt-1 text-sm text-brand-gray-light whitespace-nowrap" title="Очки рейтинга">
                                                        {{ \App\Support\Points::format($row['points']) }}
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ol>
                                    @else
                                        <div class="bg-white text-brand-gray-light text-sm px-3 py-4">Нет данных</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="text-center text-brand-gray-light py-8">
                Итоги сезонов появятся после публикации первых результатов регат.
            </div>
        @endif
    </div>
</section>

<x-feedback-section>

</x-feedback-section>
</x-public-layout>
