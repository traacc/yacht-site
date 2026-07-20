{{-- resources/views/livewire/regattas-list.blade.php --}}
<section x-data="{view: @entangle('view')}" class="md:py-12 py-4 reggata-list">
    <div class="container mx-auto">
        <div class="reggata-list__header flex flex-col-reverse md:flex-row md:items-center justify-between mb-6">
            <div class="reggata-list__filter md:flex gap-4 flex-col md:flex-row font-medium hidden">
                <button wire:click="setFilter('all')" class="reggata-list__filter-btn p-4 cursor-pointer text-center {{ $filter === 'all' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]' }}">Все</button>
                <button wire:click="setFilter('closest')" class="reggata-list__filter-btn p-4 cursor-pointer text-center {{ $filter === 'closest' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]' }}">Ближайшие</button>
                <button wire:click="setFilter('upcoming')" class="reggata-list__filter-btn p-4 cursor-pointer text-center {{ $filter === 'upcoming' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]' }}">Планируемые</button>
                <button wire:click="setFilter('finished')" class="reggata-list__filter-btn p-4 cursor-pointer text-center {{ $filter === 'finished' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]' }}">Состоявшиеся</button>
            </div>
            <div class="flex items-center gap-4">
                {{-- Выбор года (сезона) --}}
                

                <div class="reggata-list__view">
                    <button class="p-2" wire:click="setView('grid')" :class="view === 'grid' ? 'text-[#2D92CE]' : 'text-[#2E325C]'">
                        {!! file_get_contents(public_path('images/icons/grid-view.svg')) !!}
                    </button>
                    <button class="p-2" wire:click="setView('list')" :class="view === 'list' ? 'text-[#2D92CE]' : 'text-[#2E325C]'">
                        {!! file_get_contents(public_path('images/icons/list-view.svg')) !!}
                    </button>
                </div>
            </div>
        </div>
        <div class="reggata-list__items grid grid-cols-2 lg:grid-cols-3 gap-3 md:gap-6" x-show="view === 'grid'">
            @forelse ($regattas as $regatta)

            <div class="bg-[#F8F8F8] overflow-hidden w-full font-sans">
                <div class="relative">
                    <img
                        src="{{ asset('images/news/news_1.webp') }}"
                        alt="{{ $regatta->name }}"
                        class="w-full h-64 object-cover"
                    />
                    @if ($regatta->series)
                    @php($seriesPosition = $regatta->seriesPosition())
                    <div class="absolute top-0 left-0 bg-[#DDEEF7] text-[#2D92CE] px-4 py-2 text-[10px] text-sm">
                        <span class="text-white font-bold text-sm uppercase">
                            {{ $regatta->series->name }}@if ($seriesPosition) ({{ $seriesPosition['position'] }}/{{ $seriesPosition['total'] }})@endif
                        </span>
                    </div>
                    @endif
                    @if ($regatta->regatta_status === \App\Enums\RegattaStatus::Closest)
                    <div class="absolute top-0 right-0 bg-[#FDE4E3] px-4 py-2 text-[10px] text-sm">
                        <span class="text-[#F24842] font-bold text-sm uppercase">БЛИЖАЙШАЯ РЕГАТА</span>
                    </div>
                    @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Upcoming)
                    <div class="absolute top-0 right-0 bg-[#ECECEC] px-4 py-2 text-[10px] text-sm">
                        <span class="text-brand-gray-light font-bold text-sm uppercase">Планируемые</span>
                    </div>
                    @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Finished)
                    <div class="absolute top-0 right-0 bg-[#E6F4EA] px-4 py-2 text-[10px] text-sm">
                        <span class="text-[#157949] font-bold text-sm uppercase">Завершена</span>
                    </div>
                    @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Active)
                    <div class="absolute top-0 right-0 bg-[#FFF3E0] px-4 py-2 text-[10px] text-sm">
                        <span class="text-[#E67E22] font-bold text-sm uppercase">Идёт сейчас</span>
                    </div>
                    @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Cancelled)
                    <div class="absolute top-0 right-0 bg-[#FDE4E3] px-4 py-2 text-[10px] text-sm">
                        <span class="text-[#F24842] font-bold text-sm uppercase">Отменена</span>
                    </div>
                    @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Postponed)
                    <div class="absolute top-0 right-0 bg-[#FFF3E0] px-4 py-2 text-[10px] text-sm">
                        <span class="text-[#E67E22] font-bold text-sm uppercase">Перенесена</span>
                    </div>
                    @endif
                    @if ($regatta->regatta_status != \App\Enums\RegattaStatus::Postponed)
                    <div class="absolute bottom-0 left-0 bg-[#F8F8F8] text-[#2E325C] px-4 py-2  text-[10px] text-sm">
                        <span class="font-bold text-sm tracking-wide">{{ $regatta->dateRange() }}</span>
                    </div>
                    @endif
                </div>

                <div class="md:px-6 md:pt-6 md:pb-7 p-2 space-y-4">
                    <h2 class="text-brand-navy font-semibold text-sm md:text-lg leading-tight">
                        {{ $regatta->name }}
                    </h2>
                    {{-- ID для судейской программы — только админу-разработчику --}}
                    @if (auth()->user()?->isDeveloperAdmin())
                    <div class="text-[10px] text-brand-gray-light">ID: {{ $regatta->external_id ?? '—' }}</div>
                    @endif

                    <div class="flex items-center gap-3 text-gray-600 text-[10px] md:text-base text-sm max-w-4 md:max-w-full">
                        <img src="{{ asset('images/icons/marker.svg') }}" alt=""> {{ $regatta->location }}
                    </div>

                    <div class="flex items-center gap-3 text-gray-600 text-[10px] md:text-base text-sm max-w-4 md:max-w-full">
                        <img src="{{ asset('images/icons/waves.svg') }}" alt=""> {{ $regatta->water_area }}
                    </div>

                    <a href="{{ route('competition-details', $regatta) }}" class="flex items-center gap-2 text-brand-navy font-bold text-sm md:text-lg hover:gap-3 transition-all duration-200 group">
                        Подробнее  →
                        <span class="text-brand-navy group-hover:translate-x-1 transition-transform duration-200">
                        </span>
                    </a>
                </div>

            </div>

            @empty
            <div class="col-span-full py-12 text-center text-brand-gray-light text-lg">
                Регаты не найдены для выбранного года.
            </div>
            @endforelse
        </div>
        <div class="reggata-list__items bg-[#F8F8F8] pb-3" x-show="view === 'list'">
            <table class="w-full text-left border-collapse bg-[#F8F8F8]">
                <thead class="sticky top-0 bg-[#F8F8F8] md:max-h-[220px]">
                    <tr>
                        <th class="py-2 a-font text-center text-lg md:text-2xl">Дата</th>
                        <th class="py-2 a-font text-center text-lg md:text-2xl">Регата</th>
                        <th class="py-2 a-font text-center text-2xl hidden md:table-cell">Серия</th>
                        <th class="py-2 a-font text-center text-2xl hidden md:table-cell">Акватория</th>
                        <th class="py-2 a-font text-center text-2xl hidden md:table-cell">Коэфф.</th>
                        <th class="py-2 a-font text-center text-2xl hidden md:table-cell">Статус</th>
                        <th class="py-2 a-font text-center"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($regattas as $regatta)
                    @php($statusBadge = match ($regatta->regatta_status) {
                        \App\Enums\RegattaStatus::Closest   => ['bg-[#FDE4E3] text-[#F24842]', 'Ближайшая регата'],
                        \App\Enums\RegattaStatus::Upcoming  => ['bg-[#ECECEC] text-brand-gray-light', 'Планируемая'],
                        \App\Enums\RegattaStatus::Finished  => ['bg-[#E6F4EA] text-[#157949]', 'Состоявшаяся'],
                        \App\Enums\RegattaStatus::Active    => ['bg-[#FFF3E0] text-[#E67E22]', 'Идёт сейчас'],
                        \App\Enums\RegattaStatus::Cancelled => ['bg-[#FDE4E3] text-[#F24842]', 'Отменена'],
                        \App\Enums\RegattaStatus::Postponed => ['bg-[#FFF3E0] text-[#E67E22]', 'Перенесена'],
                        default => null,
                    })
                    <tr class="border-t">
                        <td class="py-2 text-center">@if ($regatta->regatta_status != \App\Enums\RegattaStatus::Postponed) {{ $regatta->dateRange() }} @endif</td>
                        <td class="py-2 text-center text-brand-navy">
                            {{ $regatta->name }}
                            @if (auth()->user()?->isDeveloperAdmin())
                            <div class="text-[10px] text-brand-gray-light">ID: {{ $regatta->external_id ?? '—' }}</div>
                            @endif
                            @if ($statusBadge)
                            <div class="md:hidden mt-1">
                                <span class="{{ $statusBadge[0] }} px-3 py-1 inline-block font-semibold text-sm">{{ $statusBadge[1] }}</span>
                            </div>
                            @endif
                        </td>
                        <td class="py-2 text-center hidden md:table-cell">
                            @if ($regatta->series)
                            @php($seriesPosition = $regatta->seriesPosition())
                            {{ $regatta->series->name }}@if ($seriesPosition) ({{ $seriesPosition['position'] }}/{{ $seriesPosition['total'] }})@endif
                            @else
                            —
                            @endif
                        </td>
                        <td class="py-2 text-center hidden md:table-cell">{{ $regatta->water_area }}</td>
                        <td class="py-2 text-center hidden md:table-cell">{{ number_format($regatta->level_coefficient, 2, ',', ' ')}}</td>
                        <td class="py-2 text-center hidden md:table-cell">
                            @if ($statusBadge)
                            <div class="{{ $statusBadge[0] }} px-3 py-1 w-full max-w-[200px] inline-block font-semibold">{{ $statusBadge[1] }}</div>
                            @endif
                        </td>
                        <td class="py-2 text-center">
                            <a href="{{ route('competition-details', $regatta) }}" class="text-[#2D92CE] font-semibold hover:underline flex items-center gap-3">Подробнее {!! file_get_contents(public_path('images/icons/l-arrow-right.svg')) !!}</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-brand-gray-light">Регаты не найдены для выбранного года.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{-- Livewire-пагинация --}}
        <div class="mt-8">
            {{ $regattas->links() }}
        </div>
</section>
