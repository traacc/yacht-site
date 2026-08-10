<x-public-layout title="Результаты серий — командные итоги по сериям регат"
    description="Итоговые командные таблицы по каждой серии регат: очки за каждую регату и общий результат серии.">
<x-breadcrumbs_page title="Результаты серий">
</x-breadcrumbs_page>
<x-hero-section title="Результаты серий"
    desc="Командные итоги по каждой серии регат: очки за каждую регату серии и общий результат."
    bgImage="{{ asset('images/bg/results.webp') }}"
>
</x-hero-section>

<div class="container mx-auto py-10"
    x-data="{
        teamModal: false,
        teamModalData: null,
        scoresModal: false,
        scoresModalData: null,
        resultModal: false,
        resultModalData: null,
        openTeam(team) {
            this.teamModalData = team;
            this.teamModal = true;
        },
        openScores(data) {
            this.scoresModalData = data;
            this.scoresModal = true;
        },
        openResult(data) {
            this.resultModalData = data;
            this.resultModal = true;
        },
        initials(name) {
            if (!name) return '?';
            return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
        }
    }"
    @keydown.escape.window="teamModal = false; scoresModal = false; resultModal = false"
>
    <x-ratings-tabs :tabs="[
        'team' => ['label' => 'Командный рейтинг', 'url' => route('ratings')],
        'personal' => ['label' => 'Личный рейтинг', 'url' => route('ratings') . '#personal'],
        'series' => ['label' => 'Результаты серий', 'url' => route('series-results'), 'active' => true],
    ]" />

    @forelse($series as $serie)
        @php $regattas = $serie['standings']['regattas']; @endphp
        <section class="mb-12 teams">
            <div class="lg:p-6 bg-brand-light-bg">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <div>
                        <h2 class="section-title a-font">
                            <a href="{{ $serie['url'] }}" class="hover:text-brand-blue hover:underline transition-colors">{{ $serie['name'] }}</a>
                        </h2>
                        @if($serie['description'] && request()->boolean('desc'))
                            <p class="text-brand-gray-light mt-1">{{ $serie['description'] }}</p>
                        @endif
                    </div>
                    @if($serie['season'])
                        <span class="text-brand-dark text-lg font-semibold">Сезон {{ $serie['season'] }}</span>
                    @endif
                </div>

                <div class="overflow-x-auto p-3 md:p-6 bg-white">
                    <table class="w-full text-sm md:text-base">
                        <thead>
                            <tr class="text-lg md:text-2xl text-brand-dark border-b border-brand-border">
                                <th class="pb-2 text-center font-medium w-10 md:w-16 a-font"></th>
                                <th class="pb-2 text-left font-medium a-font">Команда</th>
                                @foreach($regattas as $i => $r)
                                    <th class="pb-2 px-2 text-center font-medium a-font" title="{{ $r['name'] }}">
                                        <div>{{ $i + 1 }}</div>
                                        @if($r['date'])
                                            <div class="text-xs text-brand-gray-light font-normal whitespace-nowrap">{{ $r['date'] }}</div>
                                        @endif
                                    </th>
                                @endforeach
                                <th class="pb-2 px-3 text-center font-medium a-font">Очки</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y font-medium">
                            @foreach($serie['standings']['standings'] as $row)
                                @php
                                    $scoresData = [
                                        'name' => $row['name'],
                                        'total' => $row['total'],
                                        'regattas' => collect($regattas)->map(fn ($r) => [
                                            'name'   => $r['name'],
                                            'date'   => $r['date'],
                                            'points' => $row['points'][$r['id']] ?? null,
                                        ])->values()->all(),
                                    ];
                                @endphp
                                <tr class="border-b border-brand-border">
                                    <td class="py-3 text-center">{{ $row['rank'] }}</td>
                                    <td class="py-3 text-left">
                                        <button
                                            type="button"
                                            class="text-brand-dark hover:text-[#2D92CE] hover:underline transition-colors cursor-pointer font-medium text-left"
                                            @click="openTeam({{ Js::from($row['team']) }})"
                                        >{{ $row['name'] }}</button>
                                    </td>
                                    @foreach($regattas as $r)
                                        @php
                                            $resultData = isset($row['points'][$r['id']]) ? [
                                                'team'    => $row['name'],
                                                'regatta' => $r['name'],
                                                'date'    => $r['date'],
                                                'place'   => $row['places'][$r['id']] ?? null,
                                                'points'  => $row['points'][$r['id']],
                                                'races'   => $row['races'][$r['id']] ?? [],
                                                'url'     => route('competition-details', $r['external_id']),
                                            ] : null;
                                        @endphp
                                        <td class="py-3 px-2 text-center">
                                            @if($resultData)
                                                <button
                                                    type="button"
                                                    class="font-bold text-brand-blue hover:text-[#2D92CE] hover:underline transition-colors cursor-pointer"
                                                    @click="openResult({{ Js::from($resultData) }})"
                                                >{{ $row['points'][$r['id']] }}</button>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="py-3 px-3 text-center">
                                        <button
                                            type="button"
                                            class="font-bold text-brand-blue hover:text-[#2D92CE] hover:underline transition-colors cursor-pointer"
                                            @click="openScores({{ Js::from($scoresData) }})"
                                        >{{ $row['total'] }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @empty
        <p class="text-center text-brand-gray-light py-20 text-lg">Результаты серий пока не опубликованы.</p>
    @endforelse

    {{-- ─── Модальное окно: Команда ─── --}}
    <div
        x-show="teamModal"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        style="display:none"
    >
        <div class="absolute inset-0 bg-black/50" @click="teamModal = false"></div>

        <div
            x-show="teamModal"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative z-10 bg-white shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto"
        >
            <template x-if="teamModalData">
                <div>
                    <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-[#EAEAEA]">
                        <h2 class="font-display text-2xl text-[#2E325C] a-font" x-text="teamModalData.name"></h2>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 transition-colors"
                            @click="teamModal = false"
                            aria-label="Закрыть"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="bg-brand-light rounded-xl px-4 py-2 text-center">
                                <div class="text-xs text-gray-500 mb-1">Очки за серию</div>
                                <div class="font-bold text-[#2E325C] text-lg" x-text="teamModalData.total_points"></div>
                            </div>
                        </div>

                        <div class="divide-y divide-[#EAEAEA] mb-4">
                            <div class="py-3 flex justify-between gap-4" x-show="teamModalData.captain && teamModalData.captain !== '—'">
                                <span class="text-gray-500 text-sm">Рулевой</span>
                                <span class="font-medium text-[#2E325C] text-sm text-right" x-text="teamModalData.captain"></span>
                            </div>
                            <div class="py-3 flex justify-between gap-4" x-show="teamModalData.yacht && teamModalData.yacht !== '—'">
                                <span class="text-gray-500 text-sm">Яхта по умолчанию</span>
                                <span class="font-medium text-[#2E325C] text-sm text-right" x-text="teamModalData.yacht"></span>
                            </div>
                        </div>

                        <h3 class="font-semibold text-[#2E325C] mb-3">Состав команды</h3>
                        <template x-if="teamModalData.members && teamModalData.members.length > 0">
                            <div class="divide-y divide-[#EAEAEA]">
                                <template x-for="(member, idx) in teamModalData.members" :key="idx">
                                    <div class="py-3 flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full overflow-hidden bg-[#2E325C] text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                <template x-if="member.avatar">
                                                    <img :src="member.avatar" :alt="member.name" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!member.avatar">
                                                    <span x-text="initials(member.name)"></span>
                                                </template>
                                            </div>
                                            <span class="font-medium text-[#2E325C]" x-text="member.name"></span>
                                        </div>
                                        <div class="text-right text-sm text-gray-500">
                                            <div x-text="member.category"></div>
                                            <div x-show="member.birthday && member.birthday !== '—'" x-text="member.birthday"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="!teamModalData.members || teamModalData.members.length === 0">
                            <p class="text-gray-400 text-sm">Состав команды не указан</p>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ─── Модальное окно: Очки по регатам ─── --}}
    <div
        x-show="scoresModal"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        style="display:none"
    >
        <div class="absolute inset-0 bg-black/50" @click="scoresModal = false"></div>

        <div
            x-show="scoresModal"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative z-10 bg-white shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto"
        >
            <template x-if="scoresModalData">
                <div>
                    <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-[#EAEAEA]">
                        <h2 class="font-display text-2xl text-[#2E325C] a-font" x-text="scoresModalData.name"></h2>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 transition-colors"
                            @click="scoresModal = false"
                            aria-label="Закрыть"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4">
                        <h3 class="font-semibold text-[#2E325C] mb-3">Очки по регатам</h3>
                        <div class="divide-y divide-[#EAEAEA]">
                            <template x-for="(regatta, idx) in scoresModalData.regattas" :key="idx">
                                <div class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <div class="font-medium text-[#2E325C]" x-text="regatta.name"></div>
                                        <div class="text-xs text-gray-500" x-show="regatta.date" x-text="regatta.date"></div>
                                    </div>
                                    <span
                                        class="font-bold text-brand-blue text-right flex-shrink-0"
                                        x-text="regatta.points !== null ? regatta.points : '—'"
                                    ></span>
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center justify-between gap-4 mt-4 pt-4 border-t border-[#EAEAEA]">
                            <span class="font-semibold text-[#2E325C]">Всего</span>
                            <span class="font-bold text-[#2E325C] text-lg" x-text="scoresModalData.total"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ─── Модальное окно: Результат команды в регате ─── --}}
    <div
        x-show="resultModal"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        style="display:none"
    >
        <div class="absolute inset-0 bg-black/50" @click="resultModal = false"></div>

        <div
            x-show="resultModal"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative z-10 bg-white shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto"
        >
            <template x-if="resultModalData">
                <div>
                    <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-[#EAEAEA]">
                        <h2 class="font-display text-2xl text-[#2E325C] a-font" x-text="resultModalData.regatta"></h2>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 transition-colors"
                            @click="resultModal = false"
                            aria-label="Закрыть"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4">
                        <div class="text-xs text-gray-500 mb-4" x-show="resultModalData.date" x-text="resultModalData.date"></div>
                        <div class="font-semibold text-[#2E325C] mb-3" x-text="resultModalData.team"></div>

                        <div class="flex items-center gap-4">
                            <div class="bg-brand-light rounded-xl px-4 py-2 text-center flex-1">
                                <div class="text-xs text-gray-500 mb-1">Место</div>
                                <div class="font-bold text-[#2E325C] text-lg" x-text="resultModalData.place !== null ? resultModalData.place : '—'"></div>
                            </div>
                            <div class="bg-brand-light rounded-xl px-4 py-2 text-center flex-1">
                                <div class="text-xs text-gray-500 mb-1">Очки</div>
                                <div class="font-bold text-brand-blue text-lg" x-text="resultModalData.points"></div>
                            </div>
                        </div>

                        <template x-if="resultModalData.races && resultModalData.races.length > 0">
                            <div class="mt-4 pt-4 border-t border-[#EAEAEA]">
                                <h3 class="font-semibold text-[#2E325C] mb-3">Результаты по гонкам</h3>
                                <div class="divide-y divide-[#EAEAEA]">
                                    <template x-for="race in resultModalData.races" :key="race.number">
                                        <div class="py-2 flex items-center justify-between gap-4">
                                            <div>
                                                <div class="font-medium text-[#2E325C]" x-text="'Гонка ' + race.number + (race.name ? ' — ' + race.name : '')"></div>
                                                <div class="text-xs text-gray-500" x-show="race.date" x-text="race.date"></div>
                                            </div>
                                            <div class="text-right flex-shrink-0">
                                                <div class="font-bold text-[#2E325C]" x-text="race.penalty_label ? race.penalty_label : (race.position !== null ? race.position : '—')"></div>
                                                <div class="text-xs text-gray-500" x-show="race.points !== null" x-text="race.points + ' очк.'"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <a
                            :href="resultModalData.url"
                            class="mt-4 inline-flex items-center gap-1 text-brand-blue hover:text-[#2D92CE] hover:underline transition-colors text-sm font-medium"
                        >Подробнее о регате</a>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<x-feedback-section></x-feedback-section>
</x-public-layout>
