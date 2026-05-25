<x-public-layout>
<x-breadcrumbs_page title="Результаты соревнований">
</x-breadcrumbs_page>
<x-hero-section title="Результаты соревнований"
desc="Итоги сезона Ассоциации CarterPro. Таблицы публикуются от новых соревнований к более ранним."
bgImage="{{ asset('images/bg/results.webp') }}"
>
</x-hero-section>

<main class="main"
    x-data="{
        teamModal: false,
        teamModalData: null,
        participantModal: false,
        participantModalData: null,
        openTeam(team) {
            this.teamModalData = team;
            this.teamModal = true;
        },
        openParticipant(p) {
            this.participantModalData = p;
            this.participantModal = true;
        }
    }"
    @keydown.escape.window="teamModal = false; participantModal = false"
>
        <div class="container">
            <div class="grid grid-cols-1 gap-4">

                <div class="bg-brand-light rounded-xl md:p-4 md:pr-0">
                    <h3 class="font-display  text-[#2E325C] text-3xl mb-4 a-font">Командный рейтинг</h3>
                    <div class="overflow-auto md:pb-6 md:pt-0 bg-white">
                        <table class="w-full text-sm md:text-base responsive-table">
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
                                                <span :class="i===0?'text-[#C2A36B]':i===1?'text-[#9FA6AD]':i===2?'text-[#B56A3A]':'opacity-0'" class="font-bold text-sm">{!! file_get_contents(public_path('images/icons/cup.svg')) !!}</span><span x-text="i+1"></span>
                                            </div>
                                        </td>
                                        <td class="py-2" data-label="Команда">
                                            <button
                                                type="button"
                                                class="text-[#2E325C] hover:text-[#C2A36B] hover:underline transition-colors cursor-pointer font-medium"
                                                @click="openTeam(team)"
                                                x-text="team.name"
                                            ></button>
                                        </td>
                                        <td class="py-2" data-label="Очки" x-text="team.total_points"></td>
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
                <div class="bg-brand-light rounded-xl md:p-4">
                    <h3 class="font-display  text-[#2E325C]  text-3xl mb-4 a-font">Личный рейтинг</h3>
                    <div class="overflow-x-auto md:pb-6 md:pt-0 bg-white">
                        <table class="w-full text-sm md:text-base responsive-table">
                            <thead>
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] bg-white sticky top-0">
                                    <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Участник</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Очки</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium"
                                x-data="{ participants: {{ Js::from($personalRatings) }} }"
                            >
                                <template x-for="(p, i) in participants" :key="i">
                                    <tr>
                                        <td class="py-2" data-label="Место">
                                            <div class="flex items-center md:justify-center gap-3">
                                                <span :class="i===0?'text-[#C2A36B]':i===1?'text-[#9FA6AD]':i===2?'text-[#B56A3A]':'opacity-0'" class="font-bold text-sm">{!! file_get_contents(public_path('images/icons/cup.svg')) !!}</span><span x-text="i+1"></span>
                                            </div>
                                        </td>
                                        <td class="py-2" data-label="Участник">
                                            <button
                                                type="button"
                                                class="text-[#2E325C] hover:text-[#C2A36B] hover:underline transition-colors cursor-pointer font-medium"
                                                @click="openParticipant(p)"
                                                x-text="p.name"
                                            ></button>
                                        </td>
                                        <td class="py-2" data-label="Очки" x-text="p.total_points"></td>
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

                        <h3 class="font-semibold text-[#2E325C] mb-3">Состав команды</h3>
                        <template x-if="teamModalData.members && teamModalData.members.length > 0">
                            <div class="divide-y divide-[#EAEAEA]">
                                <template x-for="(member, idx) in teamModalData.members" :key="idx">
                                    <div class="py-3 flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-[#2E325C] text-white flex items-center justify-center text-sm font-bold flex-shrink-0" x-text="idx + 1"></div>
                                            <span class="font-medium text-[#2E325C]" x-text="member.name"></span>
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
                        <h2 class="font-display text-2xl text-[#2E325C] a-font" x-text="participantModalData.name"></h2>
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
                                <div class="text-xs text-gray-500 mb-1">Разряд</div>
                                <div class="font-semibold text-[#2E325C]" x-text="participantModalData.category"></div>
                            </div>
                        </div>

                        <div class="divide-y divide-[#EAEAEA]">
                            <div class="py-3 flex justify-between" x-show="participantModalData.birthday && participantModalData.birthday !== '—'">
                                <span class="text-gray-500 text-sm">Дата рождения</span>
                                <span class="font-medium text-[#2E325C] text-sm" x-text="participantModalData.birthday"></span>
                            </div>
                            <div class="py-3 flex justify-between" x-show="participantModalData.email && participantModalData.email !== '—'">
                                <span class="text-gray-500 text-sm">Email</span>
                                <span class="font-medium text-[#2E325C] text-sm" x-text="participantModalData.email"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

</main>

<x-feedback-section>
</x-feedback-section>
</x-public-layout>
