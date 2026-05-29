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
                        src="{{ asset('images/news/news_1.png') }}"
                        alt="{{ $regatta->name }}"
                        class="w-full h-64 object-cover"
                    />
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
                    <div class="absolute bottom-0 left-0 bg-[#F8F8F8] text-[#2E325C] px-4 py-2  text-[10px] text-sm">
                        <span class="font-bold text-sm tracking-wide">{{ $regatta->dateRange() }}</span>
                    </div>
                </div>

                <div class="md:px-6 md:pt-6 md:pb-7 p-2 space-y-4">
                    <h2 class="text-brand-navy font-semibold text-sm md:text-lg leading-tight">
                        {{ $regatta->name }}
                    </h2>

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
            <table class="w-full text-left border-collapse responsive-table bg-[#F8F8F8]">
                <thead class="sticky top-0 bg-[#F8F8F8] md:max-h-[220px]">
                    <tr>
                        <th class="py-2 a-font text-center text-2xl">Дата</th>
                        <th class="py-2 a-font text-center text-2xl">Регата</th>
                        <th class="py-2 a-font text-center text-2xl">Локация</th>
                        <th class="py-2 a-font text-center text-2xl">Акватория</th>
                        <th class="py-2 a-font text-center text-2xl">Статус</th>
                        <th class="py-2 a-font text-center"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($regattas as $regatta)
                    <tr class="border-t">
                        <td data-label="Дата" class="py-2 text-center">{{ $regatta->dateRange() }}</td>
                        <td data-label="Регата" class="py-2 text-center text-brand-navy">{{ $regatta->name }}</td>
                        <td data-label="Локация" class="py-2 text-center">{{ $regatta->location }}</td>
                        <td data-label="Акватория" class="py-2 text-center">{{ $regatta->water_area }}</td>
                        <td data-label="Статус" class="py-2 text-center">
                            @if ($regatta->regatta_status === \App\Enums\RegattaStatus::Closest)
                            <div class="bg-[#FDE4E3] px-3 py-1 w-full max-w-[200px] text-[#F24842] inline-block font-semibold">Ближайшая регата</div>
                            @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Upcoming)
                            <div class="bg-[#ECECEC] px-3 py-1 w-full max-w-[200px] text-brand-gray-light inline-block font-semibold">Планируемая</div>
                            @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Finished)
                            <div class="bg-[#E6F4EA] px-3 py-1 w-full max-w-[200px] text-[#157949] inline-block font-semibold">Состоявшаяся</div>
                            @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Active)
                            <div class="bg-[#FFF3E0] px-3 py-1 w-full max-w-[200px] text-[#E67E22] inline-block font-semibold">Идёт сейчас</div>
                            @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Cancelled)
                            <div class="bg-[#FDE4E3] px-3 py-1 w-full max-w-[200px] text-[#F24842] inline-block font-semibold">Отменена</div>
                            @elseif ($regatta->regatta_status === \App\Enums\RegattaStatus::Postponed)
                            <div class="bg-[#FFF3E0] px-3 py-1 w-full max-w-[200px] text-[#E67E22] inline-block font-semibold">Перенесена</div>
                            @endif
                        </td>
                        <td class="py-2 text-center">
                            <a href="{{ route('competition-details', $regatta) }}" class="text-[#2D92CE] font-semibold hover:underline flex items-center gap-3">Подробнее {!! file_get_contents(public_path('images/icons/l-arrow-right.svg')) !!}</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-brand-gray-light">Регаты не найдены для выбранного года.</td>
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
