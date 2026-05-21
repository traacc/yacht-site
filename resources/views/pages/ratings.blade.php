<x-public-layout>
<x-breadcrumbs_page title="Результаты соревнований">
</x-breadcrumbs_page>
<x-hero-section title="Результаты соревнований"
desc="Итоги сезона Ассоциации CarterPro. Таблицы публикуются от новых соревнований к более ранним."
bgImage="{{ asset('images/bg/results.png') }}"
>
</x-hero-section>

<main class="main">
    <section class="container mx-auto mb-3 md:mb-8 mt-4 flex justify-between flex-col md:flex-row gap-y-2">
        <h2 class="a-font md:text-5xl text-2xl">Результаты регат</h2>
        <div class="controls flex gap-3">
            <div class="calendar-icon">
                <select class="border-[#C6C6C6] focus:outline-hidden h-full focus:ring-2 text-[#2E325C] pl-5 min-w-[140px]" name="year" id="">
                    <option value="2026">2026</option>
                </select>
            </div>

            <select name="team_filter" id="team_filter" class="team_filter">
                <option value="">Все статусы</option>
                <option value="">Предварительные</option>
                <option value="">Завершенные</option>
            </select>
        </div>
    </section>
    @forelse($regattas as $regatta)
        <section class="rating_1 mb-12">
            <div class="container mx-auto bg-[#F8F8F8] p-6">
                <div class="flex justify-between mb-6 flex-col md:flex-row">
                    <h3 class="a-font text-3xl">{{ $regatta->name }}</h3>
                    <a class="text-[#2E325C] text-lg font-semibold gap-2 items-center hidden md:flex">
                        <img src="{{ asset('images/icons/download.svg') }}" alt="">
                        <span>Скачать результаты PDF</span>
                    </a>
                </div>
                <div class="flex gap-6 items-center mb-6">
                    <div class="date flex gap-2 text-lg font-medium">
                        {!! file_get_contents(public_path('images/icons/calendar.svg')) !!}
                        {{ $regatta->dateRange() }}
                    </div>
                    @if($regatta->isFinished())
                        <div class="bg-[#15794933] px-3 py-1 text-[#157949] inline-block font-semibold max-w-[140px] w-full uppercase">
                            Завершено
                        </div>
                    @else
                        <div class="bg-[#C2A36B26] px-3 py-1 text-[#C2A36B] inline-block font-semibold max-w-[350px] w-full uppercase">
                            Предварительные результаты
                        </div>
                    @endif
                </div>
                @if(!$regatta->isFinished())
                <p class="mb-6">Таблица обновляется по мере обработки результатов. Финальные очки будут опубликованы после утверждения итогов соревнования.</p>
                @endif
                <div class="overflow-x-auto relative p-6 bg-white responsive-table">
                    <table class="w-full">
                        <thead>
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] ">
                                <th class="pb-2 text-center font-medium w-16 a-font">Место</th>
                                <th class="pb-2 text-center font-medium a-font">Команда</th>
                                <th class="pb-2 text-center font-medium a-font">Капитан</th>
                                <th class="pb-2 text-center font-medium a-font">Яхта</th>
                                <th class="pb-2 text-center font-medium a-font">Парус №</th>
                                <th class="pb-2 text-center font-medium a-font">Участники</th>
                                <th class="pb-2 text-center font-medium a-font">Очки</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            @forelse($regatta->results as $result)
                                <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                    <td data-label="Место" class="py-3">
                                        <div @class([
                                            'flex items-center justify-center gap-3',
                                            'text-[#C2A36B]' => $result->final_position == 1,
                                            'text-[#9FA6AD]' => $result->final_position == 2,
                                            'text-[#B56A3A]' => $result->final_position == 3,
                                            'text-transparent' => !in_array($result->final_position, [1, 2, 3]),
                                        ])>
                                            {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                                            <span class="text-brand-gray">{{ $result->final_position }}</span>
                                        </div>
                                    </td>
                                    <td data-label="Команда" class="py-3">{{ $result->team?->name ?? '—' }}</td>
                                    <td data-label="Капитан" class="py-3">{{ $result->team?->organizer?->full_name ?? '—' }}</td>
                                    <td data-label="Яхта" class="py-3">{{ $result->yacht?->name ?? '—' }}</td>
                                    <td data-label="Парус №" class="py-3">{{ $result->yacht?->vfps_number ?? '—' }}</td>
                                    <td data-label="Участники" class="py-3">
                                        <a href="#" class="text-[#2D92CE] font-medium underline hover:no-underline">
                                            {{ $result->team?->activeMembers?->count() ?? 0 }} участников
                                        </a>
                                    </td>
                                    <td data-label="Очки" class="py-3">{{ $result->total_points }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-gray-400">Нет результатов</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <a class="text-[#2E325C] text-sm font-semibold gap-2 items-center flex md:hidden justify-center mt-4">
                    <img src="{{ asset('images/icons/download.svg') }}" alt="">
                    <span>Скачать результаты PDF</span>
                </a>
            </div>
        </section>
    @empty
        <section class="py-12">
            <div class="container mx-auto text-center">
                <p class="text-brand-gray text-xl">Нет регат с опубликованными результатами.</p>
            </div>
        </section>
    @endforelse
</main>

<x-feedback-section>
</x-feedback-section>
</x-public-layout>
