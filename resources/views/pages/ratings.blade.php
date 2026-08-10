<x-public-layout title="Рейтинг яхтсменов и команд - турнирые таблицы" description="Официальные рейтинги участников: очки за гонки, текущие лидеры сезона, динамика результатов и итоговые места">
<x-breadcrumbs_page title="Рейтинги">
</x-breadcrumbs_page>
<x-hero-section title="Рейтинги"
desc="Командные и личные рейтинги участников регат текущего сезона."
bgImage="{{ asset('images/bg/results.webp') }}"
>
</x-hero-section>

<main class="main"
    x-data="{
        activeTab: window.location.hash === '#personal' ? 'personal' : 'team',
        teamModal: false,
        teamModalData: null,
        participantModal: false,
        participantModalData: null,
        regattaModal: false,
        regattaModalData: null,
        openTeam(team) {
            this.teamModalData = team;
            this.teamModal = true;
        },
        openParticipant(p) {
            this.participantModalData = p;
            this.participantModal = true;
        },
        openRegattas(row) {
            this.regattaModalData = row;
            this.regattaModal = true;
        },
        openTeamRegattas(team) {
            this.regattaModalData = {
                participants: [{
                    name: team.name,
                    total_points: team.total_points,
                    regattas: team.regattas,
                }],
            };
            this.regattaModal = true;
        },
        initials(name) {
            if (!name) return '?';
            return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
        }
    }"
    @keydown.escape.window="teamModal = false; participantModal = false; regattaModal = false"
    x-on:switch-ratings-tab.window="activeTab = $event.detail.tab"
>
        <div class="container">
            {{-- ─── Вкладки ─── --}}

            <div class="desc my-4">
                <p><b>Рейтинги</b> рассчитываются по результатам участия в соревнованиях и присваиваются зарегистрированным командам и лично участникам. Яхты, на которых гоняются команды, в рейтинге не участвуют – это лишь снаряд, на котором достигаются результаты. Наша система подразумевает, что любая команда может принимать участие в регате на любой яхте.</p>    
                <p><b>Количество рейтинговых очков</b> обратно пропорционально занятому в регате месту и зависит от количества участвовавших яхт. Пример: при 10 яхтах на старте за 1е место начисляется 10 рейтинговых очков, за 2е – 9 и т.д., за 10е -1 очко. Если команда не заявлялась, то 0 очков. Также, на количество рейтинговых очков за каждое соревнование влияет рейтинговый коэффициент регаты, который можно увидеть в карточке соответствующей регаты – на этот коэффициент будут умножены рейтинговые очки за регату.</p>
                <p><b>Командный рейтинг</b> присваивается зарегистрированной и заявленной команде. Если в заявке на регату название команды не указано, заявляется команда по названию яхты.</p>
                <p><b>Личный рейтинг</b> присваивается непосредственно заявленным участникам экипажа в составе команды. Поэтому если были приглашены временные участники, а часть основного состава не участвовала, то рейтинг будет начислен только им.</p>
                <p><b>Итоговый рейтинг</b> рассчитывается и публикуется как сумма рейтинговых очков за все прошедшие регаты текущего сезона.</p>
            </div>
            <x-ratings-tabs :tabs="[
                'team' => 'Командный рейтинг',
                'personal' => 'Личный рейтинг',
                'series' => ['label' => 'Результаты серий', 'url' => route('series-results')],
            ]" />

            <div class="grid grid-cols-1 gap-4">

                <div x-show="activeTab === 'team'" role="tabpanel" class="bg-brand-light rounded-xl md:p-4 md:pr-0">
                    <h3 class="font-display  text-[#2E325C] text-3xl mb-4 a-font">Командный рейтинг@if($ratingSeasonYear) — сезон {{ $ratingSeasonYear }}@endif</h3>
                    <div class="overflow-auto md:pb-6 md:pt-0 bg-white">
                        <table class="w-full text-sm md:text-base">
                            <thead class="sticky bg-white top-0 pt-6">
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] ">
                                    <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Команда</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Очки</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium"
                                x-data="{ teams: {{ Js::from($teamRatings) }} }"
                            >
                                <template x-for="(team, i) in teams" :key="i">
                                    <tr>
                                        <td class="py-2" data-label="Место">
                                            <div class="flex items-center md:justify-center gap-3">
                                                <span :class="team.rank===1?'text-[#C2A36B]':team.rank===2?'text-[#9FA6AD]':team.rank===3?'text-[#B56A3A]':'opacity-0'" class="font-bold text-sm">{!! file_get_contents(public_path('images/icons/cup.svg')) !!}</span><span x-text="team.rank"></span>
                                            </div>
                                        </td>
                                        <td class="py-2" data-label="Команда">
                                            <div class="flex flex-col md:items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="text-[#2E325C] hover:text-[#2D92CE] hover:underline transition-colors cursor-pointer font-medium"
                                                    @click="openTeam(team)"
                                                    x-text="team.name"
                                                ></button>
                                                <template x-if="team.members && team.members.length > 0">
                                                    <div class="flex items-center md:justify-center -space-x-2">
                                                        <template x-for="(member, idx) in team.members" :key="idx">
                                                            <div @click.stop="member.id ? Livewire.dispatch('open-user-card', { userId: member.id }) : openTeam(team)"
                                                                class="relative hover:z-10 w-8 h-8 rounded-full overflow-hidden flex items-center cursor-pointer justify-center bg-[#2E325C] text-white text-[10px] font-bold flex-shrink-0 ring-2 ring-white transition-transform hover:scale-110"
                                                                :title="member.name"
                                                            >
                                                                <template x-if="member.avatar">
                                                                    <img :src="member.avatar" :alt="member.name" class="w-full h-full object-cover">
                                                                </template>
                                                                <template x-if="!member.avatar">
                                                                    <span x-text="initials(member.name)"></span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="py-2" data-label="Очки">
                                            <button
                                                type="button"
                                                class="text-[#2D92CE] hover:text-[#2D92CE] hover:underline transition-colors cursor-pointer font-semibold"
                                                @click="openTeamRegattas(team)"
                                                x-text="team.total_points"
                                            ></button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="teams.length === 0">
                                    <tr>
                                        <td colspan="3" class="py-6 text-gray-400">Данные рейтинга пока не опубликованы</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                </div>
                <div x-show="activeTab === 'personal'" role="tabpanel" class="bg-brand-light rounded-xl md:p-4"
                    x-data="{
                        participants: {{ Js::from($personalRatings) }},
                        personalView: 'places',
                        sortField: 'points',
                        sortDir: 'desc',
                        get flatParticipants() {
                            const rows = [];
                            this.participants.forEach(group => {
                                (group.participants || []).forEach(p => {
                                    rows.push(Object.assign({}, p, { place: group.place }));
                                });
                            });
                            const dir = this.sortDir === 'asc' ? 1 : -1;
                            return rows.sort((a, b) => {
                                if (this.sortField === 'name') {
                                    return (a.name || '').localeCompare(b.name || '', 'ru') * dir;
                                }
                                const byPoints = ((a.total_points || 0) - (b.total_points || 0)) * dir;
                                if (byPoints !== 0) return byPoints;
                                return (a.name || '').localeCompare(b.name || '', 'ru');
                            });
                        },
                        sortBy(field) {
                            if (this.sortField === field) {
                                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                            } else {
                                this.sortField = field;
                                this.sortDir = field === 'name' ? 'asc' : 'desc';
                            }
                        },
                        sortArrow(field) {
                            if (this.sortField !== field) return '';
                            return this.sortDir === 'asc' ? ' ▲' : ' ▼';
                        }
                    }"
                >
                    <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                        <h3 class="font-display  text-[#2E325C]  text-3xl a-font">Личный рейтинг@if($ratingSeasonYear) — сезон {{ $ratingSeasonYear }}@endif</h3>
                        <div class="inline-flex rounded-lg overflow-hidden border border-[#EAEAEA] bg-white">
                            <button
                                type="button"
                                class="px-4 py-2 text-sm md:text-base font-medium transition-colors cursor-pointer"
                                :class="personalView === 'places' ? 'bg-[#2D92CE] text-white' : 'text-[#2E325C] hover:bg-brand-light'"
                                @click="personalView = 'places'"
                            >Места</button>
                            <button
                                type="button"
                                class="px-4 py-2 text-sm md:text-base font-medium transition-colors cursor-pointer"
                                :class="personalView === 'list' ? 'bg-[#2D92CE] text-white' : 'text-[#2E325C] hover:bg-brand-light'"
                                @click="personalView = 'list'"
                            >Список</button>
                        </div>
                    </div>

                    {{-- ─── Вид: группировка по местам ─── --}}
                    <div x-show="personalView === 'places'" class="overflow-x-auto md:pb-6 md:pt-0 bg-white">
                        <table class="w-full text-sm md:text-base">
                            <thead>
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] bg-white sticky top-0">
                                    <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Участник</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Очки</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium">
                                <template x-for="(row, i) in participants" :key="i">
                                    <tr>
                                        <td class="py-2" data-label="Место">
                                            <div class="flex items-center md:justify-center gap-3">
                                                <span :class="row.place===1?'text-[#C2A36B]':row.place===2?'text-[#9FA6AD]':row.place===3?'text-[#B56A3A]':'opacity-0'" class="font-bold text-sm">{!! file_get_contents(public_path('images/icons/cup.svg')) !!}</span><span x-text="row.place"></span>
                                            </div>
                                        </td>
                                        <td class="py-2" data-label="Участник">
                                            <div class="flex items-center md:justify-center -space-x-3">
                                                <template x-for="(p, j) in row.participants" :key="j">
                                                    <button
                                                        type="button"
                                                        class="relative hover:z-10 w-10 h-10 rounded-full overflow-hidden flex items-center justify-center bg-[#2E325C] text-white text-xs font-bold ring-2 ring-white hover:ring-[#2D92CE] hover:scale-110 transition-all cursor-pointer flex-shrink-0"
                                                        @click="p.id ? Livewire.dispatch('open-user-card', { userId: p.id }) : openParticipant(p)"
                                                        :title="p.name"
                                                    >
                                                        <template x-if="p.avatar">
                                                            <img :src="p.avatar" :alt="p.name" class="w-full h-full object-cover">
                                                        </template>
                                                        <template x-if="!p.avatar">
                                                            <span x-text="initials(p.name)"></span>
                                                        </template>
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="py-2" data-label="Очки">
                                            <button
                                                type="button"
                                                class="text-[#2D92CE] hover:text-[#2D92CE] hover:underline transition-colors cursor-pointer font-semibold"
                                                @click="openRegattas(row)"
                                                x-text="row.total_points"
                                            ></button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="participants.length === 0">
                                    <tr>
                                        <td colspan="3" class="py-6 text-gray-400">Данные рейтинга пока не опубликованы</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- ─── Вид: список с сортировкой ─── --}}
                    <div x-show="personalView === 'list'" class="overflow-x-auto md:pb-6 md:pt-0 bg-white flex justify-center">
                        <table class="w-full max-w-[540px] text-sm md:text-base">
                            <thead>
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] bg-white sticky top-0">
                                    <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                    <th class="pb-2 text-left md:pl-8 font-medium a-font pt-6">
                                        <button type="button" class="a-font hover:text-[#2D92CE] transition-colors cursor-pointer"
                                            @click="sortBy('name')">
                                            <span>Участник</span><span class="text-sm align-middle" x-text="sortArrow('name')"></span>
                                        </button>
                                    </th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">
                                        <button type="button" class="a-font hover:text-[#2D92CE] transition-colors cursor-pointer"
                                            @click="sortBy('points')">
                                            <span>Очки</span><span class="text-sm align-middle" x-text="sortArrow('points')"></span>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium">
                                <template x-for="(p, i) in flatParticipants" :key="p.id ?? i">
                                    <tr>
                                        <td class="py-2" data-label="Место">
                                            <div class="flex items-center md:justify-center gap-3">
                                                <span :class="p.place===1?'text-[#C2A36B]':p.place===2?'text-[#9FA6AD]':p.place===3?'text-[#B56A3A]':'opacity-0'" class="font-bold text-sm">{!! file_get_contents(public_path('images/icons/cup.svg')) !!}</span><span x-text="p.place"></span>
                                            </div>
                                        </td>
                                        <td class="py-2 text-left md:pl-8" data-label="Участник">
                                            <button
                                                type="button"
                                                class="flex items-center gap-3 group cursor-pointer md:justify-start justify-end w-full"
                                                @click="p.id ? Livewire.dispatch('open-user-card', { userId: p.id }) : openParticipant(p)"
                                                :title="p.name"
                                            >
                                                <span class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center bg-[#2E325C] text-white text-xs font-bold ring-2 ring-white group-hover:ring-[#2D92CE] transition-all flex-shrink-0 order-2 md:order-1">
                                                    <template x-if="p.avatar">
                                                        <img :src="p.avatar" :alt="p.name" class="w-full h-full object-cover">
                                                    </template>
                                                    <template x-if="!p.avatar">
                                                        <span x-text="initials(p.name)"></span>
                                                    </template>
                                                </span>
                                                <span class="text-[#2E325C] group-hover:text-[#2D92CE] group-hover:underline transition-colors order-1 md:order-2" x-text="p.name"></span>
                                            </button>
                                        </td>
                                        <td class="py-2" data-label="Очки">
                                            <button
                                                type="button"
                                                class="text-[#2D92CE] hover:text-[#2D92CE] hover:underline transition-colors cursor-pointer font-semibold"
                                                @click="openRegattas({ participants: [p] })"
                                                x-text="p.total_points"
                                            ></button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="flatParticipants.length === 0">
                                    <tr>
                                        <td colspan="3" class="py-6 text-gray-400">Данные рейтинга пока не опубликованы</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

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
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/50"
            @click="teamModal = false"
        ></div>

        {{-- Panel --}}
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
                                <div class="text-xs text-gray-500 mb-1">Очки</div>
                                <div class="font-bold text-[#2E325C] text-lg" x-text="teamModalData.total_points"></div>
                            </div>
                        </div>

                        <div class="divide-y divide-[#EAEAEA] mb-4">
                            <div class="py-3 flex justify-between items-center gap-4" x-show="teamModalData.captain && teamModalData.captain !== '—'">
                                <span class="text-gray-500 text-sm">Капитан</span>
                                <div
                                    class="flex items-center gap-2 group"
                                    :class="teamModalData.captain_id ? 'cursor-pointer' : ''"
                                    @click="teamModalData.captain_id && Livewire.dispatch('open-user-card', { userId: teamModalData.captain_id })"
                                >
                                    <span class="font-medium text-[#2E325C] text-sm text-right" :class="teamModalData.captain_id ? 'group-hover:underline' : ''" x-text="teamModalData.captain"></span>
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-[#2E325C] text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                                        <template x-if="teamModalData.captain_avatar">
                                            <img :src="teamModalData.captain_avatar" :alt="teamModalData.captain" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!teamModalData.captain_avatar">
                                            <span x-text="initials(teamModalData.captain)"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div class="py-3 flex justify-between gap-4" x-show="teamModalData.yacht && teamModalData.yacht !== '—'">
                                <span class="text-gray-500 text-sm">Яхта по умолчанию</span>
                                <span class="font-medium text-[#2E325C] text-sm text-right" x-text="teamModalData.yacht"></span>
                            </div>
                        </div>

                        <h3 class="font-semibold text-[#2E325C] mb-3">
                            Состав команды
                            <x-info-tooltip text="Рядом с участником — спортивный разряд: б/р — без разряда, КМС — кандидат в мастера спорта, МС — мастер спорта, МСМК — мастер спорта международного класса, ЗМС — заслуженный мастер спорта." />
                        </h3>
                        <template x-if="teamModalData.members && teamModalData.members.length > 0">
                            <div class="divide-y divide-[#EAEAEA]">
                                <template x-for="(member, idx) in teamModalData.members" :key="idx">
                                    <div class="py-3 flex items-center justify-between gap-4">
                                        <div
                                            class="flex items-center gap-3 group"
                                            :class="member.id ? 'cursor-pointer' : ''"
                                            @click="member.id && Livewire.dispatch('open-user-card', { userId: member.id })"
                                        >
                                            <div class="w-8 h-8 rounded-full overflow-hidden bg-[#2E325C] text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                <template x-if="member.avatar">
                                                    <img :src="member.avatar" :alt="member.name" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!member.avatar">
                                                    <span x-text="initials(member.name)"></span>
                                                </template>
                                            </div>
                                            <span class="font-medium text-[#2E325C]" :class="member.id ? 'group-hover:underline' : ''" x-text="member.name"></span>
                                        </div>
                                        <div class="text-right text-sm text-gray-500">
                                            <div x-show="member.category && member.category !== '—'" x-text="member.category"></div>
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

    {{-- ─── Модальное окно: Участник ─── --}}
    <div
        x-show="participantModal"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        style="display:none"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/50"
            @click="participantModal = false"
        ></div>

        {{-- Panel --}}
        <div
            x-show="participantModal"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative z-10 bg-white shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto"
        >
            <template x-if="participantModalData">
                <div>
                    <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-[#EAEAEA]">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full overflow-hidden bg-[#2E325C] text-white flex items-center justify-center text-base font-bold flex-shrink-0">
                                <template x-if="participantModalData.avatar">
                                    <img :src="participantModalData.avatar" :alt="participantModalData.name" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!participantModalData.avatar">
                                    <span x-text="initials(participantModalData.name)"></span>
                                </template>
                            </div>
                            <h2 class="font-display text-2xl text-[#2E325C] a-font" x-text="participantModalData.name"></h2>
                        </div>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 transition-colors"
                            @click="participantModal = false"
                            aria-label="Закрыть"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4 space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-brand-light rounded-xl px-4 py-3">
                                <div class="text-xs text-gray-500 mb-1">Очки</div>
                                <div class="font-bold text-[#2E325C] text-lg" x-text="participantModalData.total_points"></div>
                            </div>
                            <div class="bg-brand-light rounded-xl px-4 py-3" x-show="participantModalData.category && participantModalData.category !== '—'">
                                <div class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                                    Разряд
                                    <x-info-tooltip text="б/р — без разряда, КМС — кандидат в мастера спорта, МС — мастер спорта, МСМК — мастер спорта международного класса, ЗМС — заслуженный мастер спорта." />
                                </div>
                                <div class="font-semibold text-[#2E325C]" x-text="participantModalData.category"></div>
                            </div>
                        </div>

                        <div class="divide-y divide-[#EAEAEA]">
                            <div class="py-3 flex justify-between" x-show="participantModalData.birthday && participantModalData.birthday !== '—'">
                                <span class="text-gray-500 text-sm">Дата рождения</span>
                                <span class="font-medium text-[#2E325C] text-sm" x-text="participantModalData.birthday"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ─── Модальное окно: Очки по регатам ─── --}}
    <div
        x-show="regattaModal"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        style="display:none"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/50"
            @click="regattaModal = false"
        ></div>

        {{-- Panel --}}
        <div
            x-show="regattaModal"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative z-10 bg-white shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto"
        >
            <template x-if="regattaModalData">
                <div>
                    <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-[#EAEAEA]">
                        <h2 class="font-display text-2xl text-[#2E325C] a-font">Очки по регатам</h2>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 transition-colors"
                            @click="regattaModal = false"
                            aria-label="Закрыть"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4 space-y-6">
                        <template x-for="(p, j) in regattaModalData.participants" :key="j">
                            <div>
                                <div class="flex items-center gap-3 mb-3"
                                     x-show="regattaModalData.participants.length > 1">
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-[#2E325C] text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                                        <template x-if="p.avatar">
                                            <img :src="p.avatar" :alt="p.name" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!p.avatar">
                                            <span x-text="initials(p.name)"></span>
                                        </template>
                                    </div>
                                    <h3 class="font-semibold text-[#2E325C]" x-text="p.name"></h3>
                                </div>

                                <template x-if="p.regattas && p.regattas.length > 0">
                                    <div class="divide-y divide-[#EAEAEA]">
                                        <template x-for="(reg, k) in p.regattas" :key="k">
                                            <div class="py-3 flex items-center justify-between gap-4">
                                                <div>
                                                    <div class="font-medium text-[#2E325C] text-sm" x-text="reg.name"></div>
                                                    <div class="text-xs text-gray-500" x-show="reg.date" x-text="reg.date"></div>
                                                </div>
                                                <span class="font-bold text-[#2D92CE] text-sm whitespace-nowrap" x-text="reg.points"></span>
                                            </div>
                                        </template>
                                        <div class="py-3 flex items-center justify-between gap-4">
                                            <span class="font-semibold text-[#2E325C] text-sm">Всего</span>
                                            <span class="font-bold text-[#2E325C] text-sm" x-text="p.total_points"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!p.regattas || p.regattas.length === 0">
                                    <p class="text-gray-400 text-sm">Нет регат с начисленными очками</p>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

</main>

<x-feedback-section>
</x-feedback-section>
</x-public-layout>
