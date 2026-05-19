<x-public-layout>
<x-breadcrumbs_page title="Команды Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Команды Ассоциации"
desc="Зарегистрированные команды класса Carter 30, участвующие в регатах сезона."
bgImage="{{ asset('images/bg/teams.png') }}"
>
</x-hero-section>

<main x-data="teamsApp()" class="main">
    <section class="py-12 reggata-list">
        <div class="max-w-(--breakpoint-2xl) mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between mb-6">
                <h2 class="section-title a-font">Зарегистрированные команды</h2>
                <div class="flex gap-2">
                    <button class="bg-[#2D92CE] text-white hover:bg-[#0074CC] py-2 px-4 transition-colors">Зарегистрировать команду</button>
                    <div class="flex gap-2">
                        <button @click="view = 'grid'" :class="view === 'grid' ? 'text-[#2D92CE]' : 'text-[#2E325C]'" class="p-2">
                            {!! file_get_contents(public_path('images/icons/grid-view.svg')) !!}
                        </button>
                        <button @click="view = 'list'" :class="view === 'list' ? 'text-[#2D92CE]' : 'text-[#2E325C]'" class="p-2">
                            {!! file_get_contents(public_path('images/icons/list-view.svg')) !!}
                        </button>
                    </div>
                </div>
            </div>
            <div class="searchbar flex flex-col md:flex-row gap-4 mb-6">
                <input class="w-full border-0 bg-[#F8F8F8] focus:outline-hidden py-2 px-4" type="text" placeholder="Поиск участника">
                <select name="team_filter" id="team_filter" class="team_filter">
                    <option value="">Рейтинг: по убыванию</option>
                </select>
            </div>
            <div class="reggata-list__items grid grid-cols-2 lg:grid-cols-3 gap-6" x-show="view === 'grid'">
                @foreach($teams as $team)

                <div class="bg-[#F8F8F8] overflow-hidden w-full font-sans">
                    <div class="relative">
                        <img
                            src="{{ asset('images/news/news_1.png') }}"
                            alt="{{ $team->name }}"
                            class="w-full h-64 object-cover"
                        />
                    </div>

                    <div class="px-4 pt-4 pb-7 space-y-4">
                        <div class="text-brand-navy font-semibold leading-tight flex flex-col md:flex-row justify-between md:items-center">
                            <div class="font-semibold text-lg">{{ $team->name }}</div>
                            <div class="text-base">
                                <span class="font-semibold">
                                    Капитан:
                                </span>
                                <span class="font-medium">
                                    {{ $team->organizer?->full_name ?? '—' }}
                                </span>
                            </div>
                        </div>

                        <button @click="setTeam({{ $loop->index }})" class="flex items-center gap-2 text-brand-navy font-semibold text-lg hover:gap-3 transition-all duration-200 group">
                            Подробнее
                            <span class="text-brand-navy group-hover:translate-x-1 transition-transform duration-200">
                            </span>
                        </button>
                    </div>

                </div>

                @endforeach
            </div>
            <div class="reggata-list__items" x-show="view === 'list'">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th class="py-2 a-font text-center text-2xl">Команда</th>
                            <th class="py-2 a-font text-center text-2xl">Капитан</th>
                            <th class="py-2 a-font text-center text-2xl">Статус</th>
                            <th class="py-2 a-font text-center text-2xl">Участие в регатах</th>
                            <th class="py-2 a-font text-center text-2xl">Рейтинг</th>
                            <th class="py-2 a-font text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($teams as $team)
                        <tr class="border-t">
                            <td class="py-2 text-center">{{ $team->name }}</td>
                            <td class="py-2 text-center">{{ $team->organizer?->full_name ?? '—' }}</td>
                            <td class="py-2 text-center">
                                @if($team->is_archived)
                                <div class="bg-[#F2484233] px-3 py-1 text-[#F24842] inline-block font-semibold max-w-[150px] w-full">Неактивная</div>
                                @else
                                <div class="bg-[#15794926] px-3 py-1 text-[#157949] inline-block font-semibold max-w-[150px] w-full">Активная</div>
                                @endif
                            </td>
                            <td class="py-2 text-center">{{ $team->regattaEntries->count() }} регат{{ $team->regattaEntries->count() === 1 ? 'а' : ($team->regattaEntries->count() >= 2 && $team->regattaEntries->count() <= 4 ? 'ы' : '') }}</td>
                            <td class="py-2 text-center">{{ $team->ratings->where('rating_type', 'team')->sortByDesc(fn ($r) => $r->season?->year ?? 0)->first()?->rank_position ?? '—' }}</td>
                            <td class="py-2 text-center">
                                <a href="#" @click.prevent="setTeam({{ $loop->index }})" class="text-[#2D92CE] font-semibold hover:underline">Подробнее</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination flex justify-center mt-10">
                {{ $teams->links() }}
            </div>
    </section>

    <div x-show="team_modal_open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 team-modal">
        <!-- Модальное окно для подробной информации о команде -->
    <div @click.away="team_modal_open = false"  class="relative p-6 max-w-[1000px] max-h-[80vh] overflow-y-auto bg-white gap-6"

        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
    >
        <div class="info flex flex-col md:flex-row gap-6 mb-8">
            <div class="photo max-w-1/2 shrink-0">
                <img class="max-w-full" :src="activeTeam?.photo" alt="">
            </div>
            <div class="content">
                <div class="flex mb-6">
                    <h4 class="a-font text-3xl text-[#2E325C]" x-text="activeTeam?.name"></h4>
                    <button @click="team_modal_open = false" class="ml-auto text-2xl font-bold">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
                </div>
                <p class="mb-6" x-text="activeTeam?.description"></p>
                <table>
                    <tr>
                        <td class="pr-4">Статус</td>
                        <td x-text="activeTeam?.status"></td>
                    </tr>
                    <tr>
                        <td class="pr-4">Капитан</td>
                        <td x-text="activeTeam?.captain"></td>
                    </tr>
                    <tr>
                        <td class="pr-4">Рейтинг</td>
                        <td x-text="activeTeam?.rating"></td>
                    </tr>
                    <tr>
                        <td class="pr-4">Участие в регатах</td>
                        <td x-text="activeTeam?.participation_count + ' регат' + (activeTeam?.participation_count === 1 ? 'а' : (activeTeam?.participation_count >= 2 && activeTeam?.participation_count <= 4 ? 'ы' : ''))"></td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="about text-[#2E325C] p-4 bg-[#F8F8F8] mb-8">
            <h5 class=" a-font text-3xl mb-6">О команде</h5>
            <p class="mb-6" x-text="activeTeam?.description"></p>
        </div>
        <div class="members">
            <div class="members-header flex flex-col md:flex-row items-center justify-between mb-6">
                <h5 class=" a-font text-3xl">Состав команды</h5>
                <div class="download flex gap-2 items-center"><img src="{{ asset('images/icons/download.svg') }}" alt=""> Скачать историю команды</div>
            </div>
            <div class="overflow-y-auto max-h-[180px] relative custom-scroll mb-8">
            <table class="w-full border-collapse bg-[#F8F8F8] responsive-table">
                <thead>
                    <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                        <th class="pt-2 pb-2 text-center font-medium a-font">Участник</th>
                        <th class="pt-2 pb-2 text-center font-medium a-font">Дата рождения</th>
                        <th class="pt-2 pb-2 text-center font-medium a-font">Разряд</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-center font-medium">
                    <template x-if="activeTeam?.members?.length">
                        <template x-for="(member, i) in activeTeam.members" :key="i">
                            <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                <td data-label="Участник" class="py-3" x-text="member.name"></td>
                                <td data-label="Дата рождения" class="py-3" x-text="member.birthday"></td>
                                <td data-label="Разряд" class="py-3" x-text="member.category"></td>
                            </tr>
                        </template>
                    </template>
                    <template x-if="!activeTeam?.members?.length">
                        <tr>
                            <td class="py-3 text-center" colspan="3">Нет данных</td>
                        </tr>
                    </template>
                </tbody>
            </table>
            </div>
        </div>
        <script>
            function participation_status(entry) {
                const status = entry.status;
                const place = entry.place;
                if(status === 'pending') {
                    return '<div class="bg-[#A88C5833] px-3 py-1 text-[#A88C58] inline-block font-semibold max-w-[150px] w-full">Заявка подана</div>';
                } else if (status === 'approved' && !place) {
                    return '<div class="bg-[#2D92CE33] px-3 py-1 text-[#2D92CE] inline-block font-semibold max-w-[150px] w-full">Участвует</div>';
                } else if (place == '1') {
                    return `
                    <div class="flex items-center justify-center gap-3 text-[#C2A36B]">
                    {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                    <span class="text-brand-gray">${place}</span>
                    </div>
                    `;
                } else if (place == '2') {
                    return `
                    <div class="flex items-center justify-center gap-3 text-[#9FA6AD]">
                    {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                    <span class="text-brand-gray">${place}</span>
                    </div>
                    `;
                } else if (place == '3') {
                    return `
                    <div class="flex items-center justify-center gap-3 text-[#B56A3A]">
                    {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                    <span class="text-brand-gray">${place}</span>
                    </div>
                    `;
                }
                else {
                    return `
                    <div class="flex items-center justify-center gap-3 text">
                    {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                    <span class="text-brand-gray">${place ?? '—'}</span>
                    </div>
                    `;
                }
            }
        </script>
        <div class="participation mb-8">

            <div class="participation-header flex items-center justify-between mb-6">
                <h5 class=" a-font text-3xl">Участие в регатах</h5>
                <div class="calendar-icon">
                    <select x-model="selectedYear" class="border-[#C6C6C6] focus:outline-hidden focus:ring-2 text-[#2E325C] pl-5 w-[100px]" name="year" id="">
                        <template x-for="year in activeTeam?.years || []" :key="year">
                            <option :value="year" x-text="year"></option>
                        </template>
                    </select>
                </div>

            </div>
                <div class="overflow-y-auto max-h-[180px] relative custom-scroll">
                    <table class="w-full border-collapse bg-[#F8F8F8] responsive-table">
                        <thead>
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                                <th class="pt-2 pb-2 text-center font-medium a-font">Регата</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Яхта</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Дата регаты</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Дата регистрации</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Статус</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            <template x-if="filteredParticipation.length">
                                <template x-for="(p, i) in filteredParticipation" :key="i">
                                    <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                        <td data-label="Регата" class="py-3" x-text="p.regatta"></td>
                                        <td data-label="Яхта" class="py-3" x-text="p.yacht"></td>
                                        <td data-label="Дата регаты" class="py-3" x-text="p.date_event"></td>
                                        <td data-label="Дата регистрации" class="py-3" x-text="p.date_registration"></td>
                                        <td data-label="Статус" class="py-3" x-html="participation_status(p)"></td>
                                    </tr>
                                </template>
                            </template>
                            <template x-if="!filteredParticipation.length">
                                <tr>
                                    <td class="py-3 text-center" colspan="5">Нет данных</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="gallery">
                <h5 class=" a-font text-3xl mb-6">Галерея</h5>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <template x-if="activeTeam?.gallery?.length">
                        <template x-for="(img, i) in activeTeam.gallery" :key="i">
                            <div class="card bg-[#F8F8F8]">
                                <img :src="img" alt="">
                            </div>
                        </template>
                    </template>
                    <template x-if="!activeTeam?.gallery?.length">
                        <p class="text-gray-500 col-span-full">Нет фотографий</p>
                    </template>
                </div>
            </div>
        </div>

    </div>
</main>
<script>
function teamsApp() {
    return {
        view: 'grid',
        team_modal_open: false,
        activeTeam: null,
        selectedYear: null,
        teams: @json($teamsJson),
        setTeam(index) {
            this.activeTeam = this.teams[index];
            this.selectedYear = this.activeTeam?.years?.[0] ?? null;
            this.team_modal_open = true;
        },
        get filteredParticipation() {
            if (!this.activeTeam?.participation) return [];
            if (!this.selectedYear) return this.activeTeam.participation;
            return this.activeTeam.participation.filter(p => p.year == this.selectedYear);
        }
    }
}
</script>



<x-feedback-section>

</x-feedback-section>
</x-public-layout>
