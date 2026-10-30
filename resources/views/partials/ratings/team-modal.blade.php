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

