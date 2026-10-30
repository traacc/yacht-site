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
                                        <div class="pb-2 flex items-center gap-4 text-xs text-gray-500">
                                            <span class="flex-1 min-w-0">Регата</span>
                                            <span class="w-16 shrink-0 text-center">Место</span>
                                            <span class="w-16 shrink-0 text-right">Очки</span>
                                        </div>
                                        <template x-for="(reg, k) in p.regattas" :key="k">
                                            <div class="py-3 flex items-center gap-4">
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-medium text-[#2E325C] text-sm" x-text="reg.name"></div>
                                                    <div class="text-xs text-gray-500" x-show="reg.date" x-text="reg.date"></div>
                                                </div>
                                                <span class="w-16 shrink-0 text-center font-medium text-[#2E325C] text-sm whitespace-nowrap" x-text="reg.places && reg.places.length ? reg.places.join(', ') : '—'"></span>
                                                <span class="w-16 shrink-0 text-right font-bold text-[#2D92CE] text-sm whitespace-nowrap" x-text="reg.points"></span>
                                            </div>
                                        </template>
                                        <div class="py-3 flex items-center gap-4">
                                            <span class="flex-1 min-w-0 font-semibold text-[#2E325C] text-sm">Всего</span>
                                            <span class="w-16 shrink-0"></span>
                                            <span class="w-16 shrink-0 text-right font-bold text-[#2E325C] text-sm whitespace-nowrap" x-text="p.total_points"></span>
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

