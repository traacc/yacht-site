{{-- resources/views/livewire/teams-list.blade.php --}}
<section x-data="teamsApp()" class="md:py-12 py-4 reggata-list">
    <div class="container mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between mb-6">
            <h2 class="section-title a-font mb-4 tracking-tighter md:tracking-normal">Зарегистрированные команды</h2>
            <div class="flex gap-1 md:gap-2 justify-between w-full md:w-auto">
                @guest
                <button @click="$dispatch('open-login-modal', { tab: 'register' })" class="bg-[#2D92CE] text-white hover:bg-[#0074CC] py-2 px-4 transition-colors cursor-pointer">Зарегистрировать команду  →</button>
                @else
                <a href="/user/teams/?action=create" class="bg-[#2D92CE] text-white hover:bg-[#0074CC] py-2 px-1.5 sm:px-2 md:px-4 transition-colors tracking-tighter md:tracking-normal">Зарегистрировать команду  →</a>
                @endguest
                <div class="flex gap-1 md:gap-2">
                    <button wire:click="setView('grid')" :class="$wire.view === 'grid' ? 'text-[#2D92CE]' : 'text-[#2E325C]'" class="p-2">
                        {!! file_get_contents(public_path('images/icons/grid-view.svg')) !!}
                    </button>
                    <button wire:click="setView('list')" :class="$wire.view === 'list' ? 'text-[#2D92CE]' : 'text-[#2E325C]'" class="p-2">
                        {!! file_get_contents(public_path('images/icons/list-view.svg')) !!}
                    </button>
                </div>
            </div>
        </div>

        {{-- Поиск и сортировка --}}
        <div class="searchbar flex flex-col md:flex-row gap-4 mb-6">
            <input
                wire:model.live.debounce.300ms="search"
                class="w-full border-0 bg-[#F8F8F8] focus:outline-hidden py-2 px-4"
                type="text"
                placeholder="Поиск команды"
            >
            <select wire:model.live="sort" name="team_sort" id="team_sort" class="team_filter">
                <option value="name">По названию (А-Я)</option>
                <option value="rating">Рейтинг: по убыванию</option>
                <option value="newest">Сначала новые</option>
            </select>
        </div>

        {{-- Grid view --}}
        <div class="reggata-list__items grid grid-cols-1 lg:grid-cols-3 gap-6" x-show="$wire.view === 'grid'">
            @forelse($teams as $team)
            <div class="bg-[#F8F8F8] overflow-hidden w-full font-sans">
                <div class="relative">
                    <img
                        src="{{ $team->picture ? Storage::url($team->picture) : asset('images/news/news_1.webp') }}"
                        alt="{{ $team->name }}"
                        class="w-full h-64 object-cover"
                    />
                </div>

                <div class="md:px-4 px-2 pt-4 pb-7 space-y-4">
                    <div class="text-brand-navy font-semibold leading-tight flex flex-col justify-between md:items-start md:h-[58px]">
                        <div class="font-semibold text-lg">{{ $team->name }}</div>
                        <div class="text-base">
                            <span class="font-semibold">Капитан:</span>
                            <span class="font-medium">{{ $team->organizer?->name ?? '—' }}</span>
                        </div>
                    </div>

                    <button @click="setTeam({{ $loop->index }})" class="flex items-center gap-2 text-brand-navy font-semibold text-lg hover:gap-3 transition-all duration-200 group">
                        Подробнее  →
                        <span class="text-brand-navy group-hover:translate-x-1 transition-transform duration-200"></span>
                    </button>
                </div>
            </div>
            @empty
            <div class="col-span-full py-12 text-center text-brand-gray-light text-lg">
                Команды не найдены.
            </div>
            @endforelse
        </div>

        {{-- List view --}}
        <div class="reggata-list__items" x-show="$wire.view === 'list'">
            <table class="w-full text-left border-collapse bg-[#F8F8F8]">
                <thead class="text-sm lg:text-2xl">
                    <tr>
                        <th class="py-2 a-font text-center">Команда</th>
                        <th class="py-2 a-font text-center">Капитан</th>
                        <th class="py-2 a-font text-center">Рейтинг</th>
                        <th class="py-2 a-font text-center"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teams as $team)
                    <tr class="border-t text-sm lg:text-2xl">
                        <td data-label="Команда" class="py-2 text-center">{{ $team->name }}</td>
                        <td data-label="Капитан" class="py-2 text-center">{{ $team->organizer?->name ?? '—' }}</td>
                        <td data-label="Рейтинг" class="py-2 text-center">{{ $team->teamRatings->sortByDesc(fn ($r) => $r->season?->year ?? 0)->first()?->rank_position ?? '—' }}</td>
                        <td data-label="" class="py-2 text-center">
                            <a href="#" @click.prevent="setTeam({{ $loop->index }})" class="text-[#2D92CE] font-semibold hover:underline [&>span]:hidden md:[&>span]:inline">Подробнее  <span>→</span></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-brand-gray-light">Команды не найдены.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Пагинация --}}
        <div class="pagination flex justify-center mt-10">
            {{ $teams->links() }}
        </div>
    </div>

    {{-- Модальное окно команды --}}
    <div x-show="team_modal_open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 team-modal">
        <div @click.away="team_modal_open = false" class="relative p-3 md:p-6 max-w-[1000px] max-h-[80vh] overflow-y-auto bg-white gap-6"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="info flex flex-col md:flex-row gap-6 mb-8">
                <div class="photo max-w-1/2 shrink-0 hidden md:block">
                    <img class="max-w-full" :src="activeTeam?.photo" alt="">
                </div>
                <div class="content text-sm md:text-base">
                    <div class="flex mb-6">
                        <h4 class="a-font text-lg md:text-3xl text-[#2E325C]" x-text="activeTeam?.name"></h4>
                        <button @click="team_modal_open = false" class="ml-auto text-2xl font-bold">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
                    </div>
                    <p class="mb-6" x-text="activeTeam?.description"></p>
                    <table>
                        <tr>
                            <td class="pr-4">ID</td>
                            <td x-text="activeTeam?.external_id"></td>
                        </tr>
                        <tr>
                            <td class="pr-4">Дата регистрации</td>
                            <td x-text="activeTeam?.created_at"></td>
                        </tr>
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
                    <template x-if="activeTeam?.can_edit">
                        <a :href="activeTeam?.edit_url" class="inline-flex items-center gap-2 mt-4 text-[#2D92CE] font-semibold hover:underline text-sm md:text-base">
                            Редактировать команду →
                        </a>
                    </template>
                </div>
            </div>
            <div class="photo shrink-0 md:hidden">
                <img class="max-w-full" :src="activeTeam?.photo" alt="">
            </div>
            <div class="about text-[#2E325C] p-4 bg-[#F8F8F8] mb-8 text-sm md:text-base">
                <h5 class="a-font text-lg md:text-3xl mb-6">О команде</h5>
                <p class="mb-6" x-text="activeTeam?.description"></p>
            </div>
            <div class="members">
                <div class="members-header flex flex-col md:flex-row items-center justify-between mb-6">
                    <h5 class="a-font text-lg md:text-3xl">Состав команды</h5>
                    <a :href="activeTeam?.download_url" target="_blank" class="download flex gap-2 items-center"><img src="{{ asset('images/icons/download.svg') }}" alt=""> Скачать историю команды</a>
                </div>
                <div class="overflow-y-auto max-h-[180px] relative custom-scroll mb-8">
                    <table class="w-full border-collapse bg-[#F8F8F8]">
                        <thead>
                            <tr class="md:text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                                <th class="pt-2 pb-2 text-center font-medium a-font">Участник</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Дата рождения</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Разряд</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium text-sm md:text-base">
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
                                    <td class="pl-0! text-left!" colspan="3">Нет данных</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="participation mb-8">
                <div class="participation-header flex items-center justify-between mb-6">
                    <h5 class="a-font text-lg md:text-3xl">Участие в регатах</h5>
                    <div class="calendar-icon">
                        <select x-model="selectedYear" class="border-[#C6C6C6] w-[140px] focus:outline-hidden focus:ring-2 text-[#2E325C] pl-5" name="year" id="">
                            <template x-for="year in activeTeam?.years || []" :key="year">
                                <option :value="year" x-text="year"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div class="overflow-y-auto max-h-[180px] relative custom-scroll">
                    <table class="w-full border-collapse bg-[#F8F8F8]">
                        <thead>
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                                <th class="pt-2 pb-2 text-center font-medium a-font">Регата</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Дата регаты</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Яхта</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            <template x-if="filteredParticipation.length">
                                <template x-for="(p, i) in filteredParticipation" :key="i">
                                    <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                        <td data-label="Регата" class="py-3" x-text="p.regatta"></td>
                                        <td data-label="Дата регаты" class="py-3" x-text="p.date_event"></td>
                                        <td data-label="Яхта" class="py-3" x-text="p.yacht"></td>
                                    </tr>
                                </template>
                            </template>
                            <template x-if="!filteredParticipation.length">
                                <tr>
                                    <td class="pl-0! text-left!" colspan="5">Нет данных</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="upcoming mb-8">
                <h5 class="a-font text-lg md:text-3xl mb-6">Заявлена на регаты</h5>
                <div class="overflow-y-auto max-h-[180px] relative custom-scroll">
                    <table class="w-full border-collapse bg-[#F8F8F8]">
                        <thead>
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                                <th class="pt-2 pb-2 text-center font-medium a-font">Регата</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Дата регаты</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Яхта</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            <template x-if="activeTeam?.upcoming_entries?.length">
                                <template x-for="(e, i) in activeTeam.upcoming_entries" :key="i">
                                    <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                        <td data-label="Регата" class="py-3" x-text="e.regatta"></td>
                                        <td data-label="Дата регаты" class="py-3" x-text="e.date_event"></td>
                                        <td data-label="Яхта" class="py-3" x-text="e.yacht"></td>
                                    </tr>
                                </template>
                            </template>
                            <template x-if="!activeTeam?.upcoming_entries?.length">
                                <tr>
                                    <td class="pl-0! text-left!" colspan="4">Нет данных</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="gallery">
                <h5 class="a-font text-lg md:text-3xl mb-6">Галерея</h5>
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
</section>

<script>
function teamsApp() {
    return {
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
