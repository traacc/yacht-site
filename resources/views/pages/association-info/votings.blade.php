<x-public-layout>
<x-breadcrumbs_page title="Голосования Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Голосования Ассоциации"
desc="Голосуйте по важным вопросам Ассоциации и участвуйте в развитии яхтенного сообщества." 
bgImage="{{ asset('images/bg/policy.webp') }}"
>
    
</x-hero-section>

{{-- ===== Активное голосование ===== --}}
<section class="md:py-10 py-6 bg-white">
    <div class="container mx-auto">
        <h2 class="section-title a-font mb-8">Активные голосования</h2>

        {{-- Информационный блок --}}
        <div class="bg-brand-blue-light flex flex-col sm:flex-row gap-4 sm:gap-6 p-5 md:p-6 mb-8 items-center">
            <div class="shrink-0 w-12 h-12 md:w-16 md:h-16 bg-brand-blue flex items-center justify-center">
                <svg class="w-6 h-6 md:w-8 md:h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 6L9 17L4 12" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <h3 class="a-font text-brand-dark text-xl md:text-2xl mb-2">Ваш голос важен</h3>
                <p class="text-brand-gray font-medium text-sm md:text-base">Результаты голосований помогают Ассоциации принимать решения, влияющие на развитие класса CarterPro и организацию регат.</p>
            </div>
        </div>

        {{-- Карточки голосований: один столбец на мобильных, два на ПК --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
            @forelse ($activeVotings as $voting)
                @php
                    $voted = $userVotedOptionIds[$voting->id] ?? [];
                    $bag   = 'voting_' . $voting->id;
                @endphp
                <div class="bg-brand-light-bg p-5 md:p-8 flex flex-col">
                    <h3 class="text-brand-dark font-semibold text-lg md:text-2xl mb-4">{{ $voting->title }}</h3>

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
                        @if ($voting->ends_at)
                            <div class="flex items-center gap-2 text-brand-gray-light font-medium text-sm md:text-base">
                                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M21 10H3M16 2V6M8 2V6M7.8 22H16.2C17.8802 22 18.7202 22 19.362 21.673C19.9265 21.3854 20.3854 20.9265 20.673 20.362C21 19.7202 21 18.8802 21 17.2V8.8C21 7.11984 21 6.27976 20.673 5.63803C20.3854 5.07354 19.9265 4.6146 19.362 4.32698C18.7202 4 17.8802 4 16.2 4H7.8C6.11984 4 5.27976 4 4.63803 4.32698C4.07354 4.6146 3.6146 5.07354 3.32698 5.63803C3 6.27976 3 7.11984 3 8.8V17.2C3 18.8802 3 19.7202 3.32698 20.362C3.6146 20.9265 4.07354 21.3854 4.63803 21.673C5.27976 22 6.11984 22 7.8 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span>Голосование открыто до {{ $voting->ends_at->isoFormat('D MMMM Y') }}</span>
                            </div>
                        @endif
                        <div class="text-brand-dark font-semibold text-sm md:text-base">Всего голосов: {{ $voting->votes_count }}</div>
                    </div>

                    @if (session('vote_cast') === $voting->id)
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 mb-4" role="alert">
                            <span class="block sm:inline">Спасибо! Ваш голос учтён.</span>
                        </div>
                    @endif

                    @error('voting_option', $bag)
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mb-4" role="alert">
                            <span class="block sm:inline">{{ $message }}</span>
                        </div>
                    @enderror

                    @auth
                        <form method="POST" action="{{ route('votings.vote', $voting) }}" class="flex flex-col flex-1">
                            @csrf
                            <div class="space-y-3 mb-6">
                                @foreach ($voting->options as $option)
                                    <label class="flex items-center gap-3 bg-white p-4 cursor-pointer group">
                                        <span class="relative shrink-0 w-6 h-6">
                                            <input type="{{ $voting->allow_multiple ? 'checkbox' : 'radio' }}"
                                                name="voting_option{{ $voting->allow_multiple ? '[]' : '' }}" value="{{ $option->id }}"
                                                @checked(in_array($option->id, $voted))
                                                class="peer appearance-none w-6 h-6 {{ $voting->allow_multiple ? 'rounded' : 'rounded-full' }} border-2 border-brand-border checked:border-brand-blue transition-colors cursor-pointer">
                                            <span class="pointer-events-none absolute inset-0 m-auto w-3 h-3 {{ $voting->allow_multiple ? 'rounded' : 'rounded-full' }} bg-brand-blue opacity-0 peer-checked:opacity-100 transition-opacity"></span>
                                        </span>
                                        <span class="text-brand-gray font-medium text-sm md:text-base">{{ $option->title }}</span>
                                    </label>
                                @endforeach
                            </div>

                            
                                <p class="text-brand-gray-light font-medium text-sm mb-3 h-4">@if (! empty($voted)) Вы уже проголосовали. Можно изменить свой выбор. @endif</p>
                            

                            <button type="submit" class="mt-auto w-full bg-brand-blue text-white font-semibold text-sm md:text-base py-4 hover:opacity-90 transition-opacity">
                                {{ empty($voted) ? 'Проголосовать' : 'Изменить голос' }}
                            </button>
                        </form>
                    @else
                        <div class="flex flex-col flex-1">
                            <div class="space-y-3 mb-6">
                                @foreach ($voting->options as $option)
                                    <div class="flex items-center gap-3 bg-white p-4">
                                        <span class="shrink-0 w-6 h-6 {{ $voting->allow_multiple ? 'rounded' : 'rounded-full' }} border-2 border-brand-border"></span>
                                        <span class="text-brand-gray font-medium text-sm md:text-base">{{ $option->title }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <a href="{{ route('login') }}" class="mt-auto block w-full text-center bg-brand-blue text-white font-semibold text-sm md:text-base py-4 hover:opacity-90 transition-opacity">
                                Войдите, чтобы проголосовать
                            </a>
                        </div>
                    @endauth
                </div>
            @empty
                <div class="md:col-span-2 bg-brand-light-bg text-center text-brand-gray-light font-medium p-8">
                    Сейчас нет активных голосований
                </div>
            @endforelse
        </div>
    </div>
</section>

{{-- ===== Завершенные голосования ===== --}}
<section class="md:py-10 py-6 bg-white" x-data="{ open: false, current: { title: '', date: '', total: 0, options: [] } }">
    <div class="container mx-auto">
        <h2 class="section-title a-font mb-8">Завершенные голосования</h2>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] border-collapse">
                <thead>
                    <tr class="border-b border-brand-border text-left">
                        <th class="a-font text-brand-dark uppercase text-sm md:text-base font-semibold py-4 pr-4">Голосование</th>
                        <th class="a-font text-brand-dark uppercase text-sm md:text-base font-semibold py-4 px-4">Дата завершение</th>
                        <th class="a-font text-brand-dark uppercase text-sm md:text-base font-semibold py-4 px-4">Голосов</th>
                        <th class="a-font text-brand-dark uppercase text-sm md:text-base font-semibold py-4 px-4">Статус</th>
                        <th class="py-4 pl-4"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($closedVotings as $voting)
                        @php
                            $resultPayload = [
                                'title'   => $voting->title,
                                'date'    => $voting->ends_at?->isoFormat('D MMMM Y'),
                                'total'   => $voting->votes_count,
                                'options' => $voting->options->map(fn ($o) => [
                                    'title'   => $o->title,
                                    'percent' => $voting->votes_count > 0
                                        ? (int) round($o->votes_count / $voting->votes_count * 100)
                                        : 0,
                                ])->all(),
                            ];
                        @endphp
                        <tr class="border-b border-brand-border hover:bg-brand-light-bg transition-colors cursor-pointer"
                            @click="current = @js($resultPayload); open = true">
                            <td class="text-brand-gray font-medium text-sm md:text-base py-5 pr-4 max-w-xs">{{ $voting->title }}</td>
                            <td class="text-brand-gray font-medium text-sm md:text-base py-5 px-4 whitespace-nowrap">{{ $voting->ends_at?->isoFormat('D MMMM Y') ?? '—' }}</td>
                            <td class="text-brand-gray font-medium text-sm md:text-base py-5 px-4">{{ $voting->votes_count }}</td>
                            <td class="py-5 px-4">
                                <span class="inline-block bg-green-100 text-green-700 font-medium text-xs md:text-sm px-3 py-1.5 whitespace-nowrap">{{ $voting->status->getLabel() }}</span>
                            </td>
                            <td class="py-5 pl-4">
                                <span class="text-brand-blue font-semibold text-sm md:text-base inline-flex items-center gap-2 whitespace-nowrap hover:underline">
                                    <span>Посмотреть результаты</span>
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-brand-gray-light font-medium py-8">
                                Завершённых голосований пока нет
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Модальное окно с результатами --}}
    <div x-cloak x-show="open"
         class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
         x-on:keydown.escape.window="open = false">
        <div x-show="open" class="fixed inset-0 bg-gray-500/75 transition-opacity"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @click="open = false"></div>

        <div x-show="open"
             class="relative bg-white shadow-xl sm:w-full sm:max-w-2xl sm:mx-auto p-5 md:p-8"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95">

            <div class="flex items-start justify-between gap-4 mb-6">
                <h2 class="section-title a-font text-2xl md:text-3xl">Результаты голосования</h2>
                <button type="button" @click="open = false" class="shrink-0 text-brand-gray-light hover:text-brand-dark transition-colors" aria-label="Закрыть">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            <div class="bg-brand-light-bg p-5 md:p-6 mb-6">
                <h3 class="text-brand-dark font-semibold text-lg md:text-xl mb-4" x-text="current.title"></h3>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
                    <div class="flex items-center gap-2 text-brand-gray-light font-medium text-sm md:text-base">
                        <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 10H3M16 2V6M8 2V6M7.8 22H16.2C17.8802 22 18.7202 22 19.362 21.673C19.9265 21.3854 20.3854 20.9265 20.673 20.362C21 19.7202 21 18.8802 21 17.2V8.8C21 7.11984 21 6.27976 20.673 5.63803C20.3854 5.07354 19.9265 4.6146 19.362 4.32698C18.7202 4 17.8802 4 16.2 4H7.8C6.11984 4 5.27976 4 4.63803 4.32698C4.07354 4.6146 3.6146 5.07354 3.32698 5.63803C3 6.27976 3 7.11984 3 8.8V17.2C3 18.8802 3 19.7202 3.32698 20.362C3.6146 20.9265 4.07354 21.3854 4.63803 21.673C5.27976 22 6.11984 22 7.8 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Голосование завершено <span x-text="current.date"></span></span>
                    </div>
                    <div class="text-brand-dark font-semibold text-sm md:text-base">Всего голосов: <span x-text="current.total"></span></div>
                </div>

                <div class="space-y-4">
                    <template x-for="(option, index) in current.options" :key="index">
                        <div>
                            <div class="text-brand-gray font-medium text-sm md:text-base mb-2" x-text="option.title"></div>
                            <div class="flex items-center gap-4">
                                <div class="flex-1 h-3 bg-white overflow-hidden">
                                    <div class="h-full bg-brand-blue transition-all" :style="'width: ' + option.percent + '%'"></div>
                                </div>
                                <span class="text-brand-dark font-semibold text-sm md:text-base w-12 text-right" x-text="option.percent + '%'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <button type="button" @click="open = false" class="w-full bg-brand-blue text-white font-semibold text-sm md:text-base py-4 hover:opacity-90 transition-opacity">
                Закрыть
            </button>
        </div>
    </div>
</section>
<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>