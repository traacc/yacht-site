{{--
    Livewire-компонент: Результаты регат
    Режимы:
      - 'home' — одна последняя регата + топ-3, главная страница
      - 'show' — одна конкретная регата, страница регаты
      - 'list' — список регат с фильтрами, страница результатов
--}}

{{--
    Единый корневой элемент с Alpine-состоянием модального окна.
    Модальное окно управляется через Livewire ($activeTeamModal),
    Alpine используется только для CSS-анимации и закрытия по клику вне.
--}}
<div x-data="{ get modalOpen() { return {{ $activeTeamModal ? 'true' : 'false' }}; } }"
     x-effect="modalOpen ? document.body.classList.add('overflow-hidden') : document.body.classList.remove('overflow-hidden')">

    @if($mode === 'list')
    {{-- ===== РЕЖИМ: СПИСОК РЕГАТ С ФИЛЬТРАМИ ===== --}}

        <section class="container mx-auto mb-3 md:mb-8 mt-4 flex justify-between flex-col md:flex-row gap-y-2">
            <h2 class="a-font md:text-5xl text-2xl">Результаты регат</h2>
            <div class="controls flex gap-3">
                <div class="calendar-icon">
                    <select
                        wire:model.live="yearFilter"
                        class="border-[#C6C6C6] focus:outline-hidden h-full focus:ring-2 text-[#2E325C] pl-5 min-w-[140px]"
                        name="year"
                    >
                        <option value="">Все годы</option>
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <select
                    wire:model.live="statusFilter"
                    name="status_filter"
                    class="border-[#C6C6C6] focus:outline-hidden h-full focus:ring-2 text-[#2E325C] pl-5 min-w-[160px]"
                >
                    <option value="">Все статусы</option>
                    <option value="preliminary">Предварительные</option>
                    <option value="finished">Завершённые</option>
                </select>
            </div>
        </section>

        @forelse($regattas as $regatta)
            <section class="rating_1 mb-12">
                <div class="container mx-auto bg-[#F8F8F8] py-2 md:py-4">
                    <div class="flex justify-between mb-6 flex-col md:flex-row">
                        <h3 class="a-font text-lg md:text-3xl">{{ $regatta->name }}</h3>
                        @if($regatta->results->first())
                            <a href="{{ route('regatta.results.pdf', $regatta) }}" target="_blank" class="text-[#2E325C] text-lg font-semibold gap-2 items-center hidden md:flex cursor-pointer">
                                <img src="{{ asset('images/icons/download.svg') }}" alt="">
                                <span>Скачать результаты PDF</span>
                            </a>
                        @endif
                    </div>
                    <div class="flex gap-6 items-center mb-6">
                        <div class="date flex gap-2 md:text-lg font-medium">
                            {!! file_get_contents(public_path('images/icons/calendar.svg')) !!}
                            {{ $regatta->dateRange() }}
                        </div>
                        @php $isFinal = $regatta->results->first()?->isFinal() ?? false; @endphp
                        @if($isFinal)
                            <div class="bg-[#15794933] px-3 py-1 text-[#157949] inline-block font-semibold max-w-[140px] w-full uppercase">
                                Завершено
                            </div>
                        @else
                            <div class="bg-[#C2A36B26] px-3 py-1 text-[#C2A36B] inline-block font-semibold max-w-[350px] w-full uppercase">
                                Предварительные результаты
                            </div>
                        @endif
                    </div>
                    @if(! $isFinal)
                        <p class="mb-6">Таблица обновляется по мере обработки результатов. Финальные очки будут опубликованы после утверждения итогов соревнования.</p>
                    @endif
                    <div class="overflow-x-auto relative p-6 bg-white">
                        @if($regatta->results->first())
                            <a href="{{ route('regatta.results.pdf', $regatta) }}" target="_blank" class="text-[#2E325C] text-sm font-semibold gap-2 items-center flex md:hidden justify-center mt-4 cursor-pointer">
                                <img src="{{ asset('images/icons/download.svg') }}" alt="">
                                <span>Скачать результаты PDF</span>
                            </a>
                        @endif
                        <table class="w-full">
                            <thead>
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA]  hidden md:table-row">
                                    <th class="pb-2 text-center font-medium w-16 a-font">Место</th>
                                    <th class="pb-2 text-center font-medium a-font">Яхта</th>
                                    <th class="pb-2 text-center font-medium a-font">Парус №</th>
                                    <th class="pb-2 text-center font-medium a-font">Команда</th>
                                    <th class="pb-2 text-center font-medium a-font">Рулевой</th>
                                    <th class="pb-2 text-center font-medium a-font">Экипаж</th>
                                    <th class="pb-2 text-center font-medium a-font">Очки</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium">
                                @forelse($regatta->resultItems as $result)
                                    <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                        <td data-label="Место" class="py-3">
                                            <div @class([
                                                'flex items-center justify-end md:justify-center gap-3',
                                                'text-[#C2A36B]' => $result->final_position == 1,
                                                'text-[#9FA6AD]' => $result->final_position == 2,
                                                'text-[#B56A3A]' => $result->final_position == 3,
                                                'text-transparent' => !in_array($result->final_position, [1, 2, 3]),
                                            ])>
                                                {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                                                <span class="text-brand-gray">{{ $result->final_position }}</span>
                                            </div>
                                        </td>
                                        <td data-label="Яхта" class="py-3 hidden md:table-cell">{{ $result->yacht?->name ?? '—' }}</td>
                                        <td data-label="Парус №" class="py-3 hidden md:table-cell">{{ $result->yacht?->vfps_number ?? '—' }}</td>
                                        @php $crew = $crewMaps[$regatta->id][$result->team_id] ?? []; @endphp
                                        <td data-label="Команда" class="py-3">
                                            <button class="text-[#2D92CE] font-medium underline hover:no-underline md:no-underline md:text-current cursor-pointer" wire:click="openTeamModal('{{ $result->team->id }}', '{{ addslashes($result->team->name) }}', {{ json_encode($crew) }})">{{ $result->team?->name ?? '—' }}</button>
                                            <span class="md:hidden"><br>({{ $result->yacht?->name ?? '—' }})</span>
                                        </td>
                                        <td data-label="Рулевой" class="py-3 hidden md:table-cell">{{ $captainMaps[$regatta->id][$result->team_id] ?? '—' }}</td>
                                        <td data-label="Экипаж" class="py-3 hidden md:table-cell">
                                            @if(!empty($crew))
                                                <button
                                                    wire:click="openTeamModal('{{ $result->team->id }}', '{{ addslashes($result->team->name) }}', {{ json_encode($crew) }})"
                                                    class="text-[#2D92CE] font-medium underline hover:no-underline cursor-pointer bg-transparent border-0 p-0">
                                                    {{ count($crew) }} <span class="hidden md:inline">участников</span>
                                                </button>
                                            @else
                                                <span class="text-brand-gray">0 <span class="hidden md:inline">участников</span></span>
                                            @endif
                                        </td>
                                        <td data-label="Очки" class="py-3">{{ number_format($result->total_points, 1, ',', ' ') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-gray-400">Нет результатов</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </section>
        @empty
            <section class="py-12">
                <div class="container mx-auto text-center">
                    <p class="text-brand-gray text-xl">Нет регат с опубликованными результатами.</p>
                </div>
            </section>
        @endforelse

    @elseif($mode === 'show')
    {{-- ===== РЕЖИМ: СТРАНИЦА РЕГАТЫ ===== --}}

        @if($regatta && $regatta->regatta_status === \App\Enums\RegattaStatus::Finished)
            <section class="results mb-12">
                <div class="container mx-auto bg-[#F8F8F8]">
                    <div class="flex justify-between mb-6">
                        <div class="flex gap-4">
                            <h3 class="a-font text-lg md:text-3xl">Результаты</h3>
                            @if($regatta->results->first()?->isFinal())
                                <div class="bg-[#15794933] px-3 py-1 text-[#157949] inline-block font-semibold max-w-[140px] w-full uppercase">
                                    Завершено
                                </div>
                            @else
                                <div class="bg-[#A88C5833] px-3 py-1 text-[#A88C58] inline-block font-semibold max-w-[290px] w-full">
                                    Предварительные результаты
                                </div>
                            @endif
                        </div>
                        @if($regatta->results->first())
                            <a href="{{ route('regatta.results.pdf', $regatta) }}" target="_blank" class="text-[#2E325C] text-lg font-semibold flex gap-2 items-center cursor-pointer">
                                <img src="{{ asset('images/icons/download.svg') }}" alt="">
                                <span>Скачать результаты PDF</span>
                            </a>
                        @endif
                    </div>
                    <div class="overflow-x-auto relative p-2 md:p-6 bg-white">
                        <table class="w-full">
                            <thead>
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] hidden md:table-row">
                                    <th class="pb-2 text-center font-medium w-16 a-font">Место</th>
                                    <th class="pb-2 text-center font-medium a-font">Яхта</th>
                                    <th class="pb-2 text-center font-medium a-font">Парус №</th>
                                    <th class="pb-2 text-center font-medium a-font">Команда</th>
                                    <th class="pb-2 text-center font-medium a-font">Рулевой</th>
                                    <th class="pb-2 text-center font-medium a-font">Экипаж</th>
                                    <th class="pb-2 text-center font-medium a-font">Очки</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium">
                                @forelse($resultItems as $result)
                                    <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA] pb-8! md:pb-0!">
                                        <td class="py-3">
                                            <div @class([
                                                'flex items-center justify-end md:justify-center gap-3',
                                                'text-[#C2A36B]' => $result->final_position == 1,
                                                'text-[#9FA6AD]' => $result->final_position == 2,
                                                'text-[#B56A3A]' => $result->final_position == 3,
                                                'text-transparent' => !in_array($result->final_position, [1, 2, 3])
                                            ])>
                                                {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                                                <span class="text-brand-gray">{{ $result->final_position }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3  hidden md:table-cell">{{ $result->yacht?->name ?? '—' }}</td>
                                        <td class="py-3  hidden md:table-cell">{{ $result->yacht?->vfps_number ?? '—' }}</td>
                                        @php $crew = $crewMap[$result->team_id] ?? []; @endphp
                                        <td class="py-3">
                                            <button class="text-[#2D92CE] font-medium underline md:no-underline md:text-current hover:no-underline cursor-pointer" wire:click="openTeamModal('{{ $result->team->id }}', '{{ addslashes($result->team->name) }}', {{ json_encode($crew) }})">{{ $result->team?->name ?? '—' }}</button>
                                            <span class="md:hidden"><br>({{ $result->yacht?->name ?? '—' }})</span>
                                        </td>
                                        <td class="py-3  hidden md:table-cell">{{ $captainMap[$result->team_id] ?? '—' }}</td>
                                        <td class="py-3 hidden md:table-cell">
                                            
                                            @if(!empty($crew))
                                                <button
                                                    wire:click="openTeamModal('{{ $result->team->id }}', '{{ addslashes($result->team->name) }}', {{ json_encode($crew) }})"
                                                    class="text-[#2D92CE] font-medium underline hover:no-underline cursor-pointer bg-transparent border-0 p-0">
                                                    {{ count($crew) }} <span class="hidden md:inline">участников</span>
                                                </button>
                                            @else
                                                <span class="text-brand-gray">0 <span class="hidden md:inline">участников</span></span>
                                            @endif
                                        </td>
                                        <td class="py-3">{{ number_format($result->total_points, 1, ',', ' ') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-brand-gray-light">Нет результатов</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

    @else
    {{-- ===== РЕЖИМ: ГЛАВНАЯ СТРАНИЦА (home) ===== --}}

        <section class="py-12">
            <div class="container mx-auto sm:px-6 py-4 bg-[#F8F8F8]">
                @if($regatta)
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="section-title a-font">Результаты регат</h2>
                        @if($regatta->results->first())
                            <a href="{{ route('regatta.results.pdf', $regatta) }}" target="_blank" class="text-[#2E325C] text-lg font-semibold gap-2 items-center hidden md:flex cursor-pointer">
                                <img src="{{ asset('images/icons/download.svg') }}" alt="">
                                <span>Скачать результаты PDF</span>
                            </a>
                        @endif

                    </div>
                    <div class="flex justify-end">
                        <a href="{{ route('competitions') }}#results" class="text-[#2E325C] text-lg font-semibold hover:underline hidden md:block">
                            Все результаты →
                        </a>
                    </div>
                    {{-- Таблица этапа --}}
                    <section class="rating_1 mb-12">
                        <div class="bg-[#F8F8F8]">
                            <div class="flex justify-between mb-6 flex-col md:flex-row">
                                <h3 class="a-font text-3xl">{{ $regatta->name }}</h3>
                            </div>
                            <div class="flex gap-6 items-center mb-6">
                                <div class="date flex gap-2 text-sm md:text-lg font-medium">
                                    {!! file_get_contents(public_path('images/icons/calendar.svg')) !!}
                                    {{ $regatta->dateRange() }}
                                </div>
                                @php $isFinal = $regatta->results->first()?->isFinal() ?? false; @endphp
                                @if($isFinal)
                                    <div class="bg-[#15794933] px-3 py-1 text-[#157949] inline-block font-semibold max-w-[140px] w-full uppercase md:text-base text-sm">
                                        Завершено
                                    </div>
                                @else
                                    <div class="bg-[#C2A36B26] px-3 py-1 text-[#C2A36B] inline-block font-semibold max-w-[350px] w-full uppercase md:text-base text-sm">
                                        Предварительные результаты
                                    </div>
                                @endif
                            </div>
                            @if(! $isFinal)
                                <p class="mb-6">Таблица обновляется по мере обработки результатов. Финальные очки будут опубликованы после утверждения итогов соревнования.</p>
                            @endif
                            <div class="overflow-x-auto relative p-2 md:p-6 md:pt-0 bg-white">
                                @if($regatta->results->first())
                                    <a href="{{ route('regatta.results.pdf', $regatta) }}" target="_blank" class="text-[#2E325C] text-sm font-semibold gap-2 items-center flex md:hidden justify-center mt-4 cursor-pointer">
                                        <img src="{{ asset('images/icons/download.svg') }}" alt="">
                                        <span>Скачать результаты PDF</span>
                                    </a>
                                @endif
                                <table class="w-full">
                                    <thead class="sticky top-0 bg-white">
                                        <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA]  hidden md:table-row">
                                            <th class="pb-2 text-center font-medium pt-6 w-16 a-font">Место</th>
                                            <th class="pb-2 text-center font-medium pt-6 a-font">Команда</th>
                                            <th class="pb-2 text-center font-medium pt-6 a-font">Рулевой</th>
                                            <th class="pb-2 text-center font-medium pt-6 a-font">Яхта</th>
                                            <th class="pb-2 text-center font-medium pt-6 a-font">Парус №</th>
                                            <th class="pb-2 text-center font-medium pt-6 a-font">Участники</th>
                                            <th class="pb-2 text-center font-medium pt-6 a-font">Очки</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y text-center font-medium">
                                        @forelse($resultItems as $result)
                                            <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                                <td data-label="Место" class="py-3">
                                                    <div @class([
                                                        'flex items-center justify-end md:justify-center gap-3',
                                                        'text-[#C2A36B]' => $result->final_position == 1,
                                                        'text-[#9FA6AD]' => $result->final_position == 2,
                                                        'text-[#B56A3A]' => $result->final_position == 3,
                                                        'text-transparent' => !in_array($result->final_position, [1, 2, 3]),
                                                    ])>
                                                        {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                                                        <span class="text-brand-gray">{{ $result->final_position }}</span>
                                                    </div>
                                                </td>
                                                @php $crew = $crewMap[$result->team_id] ?? []; @endphp
                                                <td data-label="Команда" class="py-3">
                                                    <button class="text-[#2D92CE] font-medium underline hover:no-underline md:no-underline md:text-current cursor-pointer" wire:click="openTeamModal('{{ $result->team->id }}', '{{ addslashes($result->team->name) }}', {{ json_encode($crew) }})">{{ $result->team?->name ?? '—' }}</button>
                                                    <span class="md:hidden"><br>({{ $result->yacht?->name ?? '—' }})</span>
                                                </td>
                                                <td data-label="Рулевой" class="py-3 hidden md:table-cell">{{ $captainMap[$result->team_id] ?? '—' }}</td>
                                                <td data-label="Яхта" class="py-3 hidden md:table-cell">{{ $result->yacht?->name ?? '—' }}</td>
                                                <td data-label="Парус №" class="py-3 hidden md:table-cell">{{ $result->yacht?->vfps_number ?? '—' }}</td>
                                                <td data-label="Участники" class="py-3 hidden md:table-cell">
                                                    
                                                    @if(!empty($crew))
                                                        <button
                                                            wire:click="openTeamModal('{{ $result->team->id }}', '{{ addslashes($result->team->name) }}', {{ json_encode($crew) }})"
                                                            class="text-[#2D92CE] font-medium underline hover:no-underline cursor-pointer bg-transparent border-0 p-0">
                                                            {{ count($crew) }} <span class="hidden md:inline">участников</span>
                                                        </button>
                                                    @else
                                                        <span class="text-brand-gray">0 <span class="hidden md:inline">участников</span></span>
                                                    @endif
                                                </td>
                                                <td data-label="Очки" class="py-3">{{ number_format($result->total_points, 1, ',', ' ') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="py-6 text-gray-400">Нет результатов</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </section>
                @endif
                
            {{-- ===== TOP-3 РЕЙТИНГИ ===== --}}
                {{-- Топ-3 рейтинги --}}
                <div class="grid md:grid-cols-2 gap-4">
                    @if($topTeams->isNotEmpty())
                    <div class="bg-brand-light rounded-xl md:p-4 md:pr-0">
                        <h3 class="font-display text-[#2E325C] text-3xl mb-4 a-font">ТОП-3 команд сезона</h3>
                        <div class="overflow-auto md:p-6 md:pt-0 bg-white md:max-h-[220px]">
                            <table class="w-full text-sm md:text-base">
                                <thead class="sticky bg-white top-0 pt-6">
                                    <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA]">
                                        <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                        <th class="pb-2 text-center font-medium a-font pt-6">Команда</th>
                                        <th class="pb-2 text-center font-medium a-font pt-6">Очки</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-center font-medium">
                                        @foreach($topTeams as $position => $entry)
                                        @php
                                            $team   = $entry['model'];
                                            $points = $entry['points'];
                                        @endphp
                                        <tr>
                                            <td class="py-2" data-label="Место">
                                                <div class="flex items-center md:justify-center justify-end gap-3">
                                                    <span @class([
                                                        'font-bold text-sm',
                                                        'text-[#C2A36B]' => $position === 0,
                                                        'text-[#9FA6AD]' => $position === 1,
                                                        'text-[#B56A3A]' => $position === 2,
                                                        'text-transparent' => $position > 2,
                                                    ])>
                                                        {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                                                    </span>
                                                    <span>{{ $position + 1 }}</span>
                                                </div>
                                            </td>
                                            <td class="py-2" data-label="Команда">{{ $team->name }}</td>
                                            <td class="py-2" data-label="Очки">{{ $points }}</td>
                                        </tr>
                                        @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    @if($topParticipants->isNotEmpty())
                    <div class="bg-brand-light rounded-xl md:p-4">
                        <h3 class="font-display text-[#2E325C] text-3xl mb-4 a-font">ТОП-3 участников</h3>
                        <div class="overflow-x-auto md:p-6 md:pt-0 bg-white md:max-h-[220px]">
                            <table class="w-full text-sm md:text-base">
                                <thead>
                                    <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] bg-white sticky top-0">
                                        <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                        <th class="pb-2 text-center font-medium a-font pt-6">Участник</th>
                                        <th class="pb-2 text-center font-medium a-font pt-6">Очки</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-center font-medium">
                                        @foreach($topParticipants as $position => $entry)
                                        @php
                                            $participant   = $entry['model'];
                                            $points = $entry['points'];
                                        @endphp
                                        <tr>
                                            <td class="py-2" data-label="Место">
                                                <div class="flex items-center md:justify-center justify-end gap-3">
                                                    <span @class([
                                                        'font-bold text-sm',
                                                        'text-[#C2A36B]' => $position === 0,
                                                        'text-[#9FA6AD]' => $position === 1,
                                                        'text-[#B56A3A]' => $position === 2,
                                                        'text-transparent' => $position > 2,
                                                    ])>
                                                        {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                                                    </span>
                                                    <span>{{ $position + 1 }}</span>
                                                </div>
                                            </td>
                                            <td class="py-2" data-label="Участник">{{ $participant->name ?: $participant->name }}</td>
                                            <td class="py-2" data-label="Очки">{{ $points }}</td>
                                        </tr>
                                        @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    <a href="{{ route('competitions') }}#results" class="text-[#2E325C] text-sm text-center font-semibold hover:underline md:hidden">
                        Все результаты →
                    </a>
                </div>
            </div>
        </section>

    @endif

    {{-- ===== МОДАЛЬНОЕ ОКНО: СОСТАВ КОМАНДЫ ===== --}}
    {{--
        Управляется через Livewire: $activeTeamModal содержит данные команды или null.
        Alpine используется только для анимации и закрытия по клику вне окна.
    --}}
    @if($activeTeamModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @keydown.escape.window="$wire.closeTeamModal()"
        >
            <div
                class="relative p-6 w-full max-w-[1000px] bg-white"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="$wire.closeTeamModal()"
            >
                <div class="flex justify-between items-start mb-4">
                    <h4 class="a-font text-lg md:text-3xl text-brand-dark">
                        Состав команды: {{ $activeTeamModal['team_name'] }}
                    </h4>
                    <button
                        wire:click="closeTeamModal"
                        class="text-2xl font-bold leading-none text-brand-gray hover:text-brand-dark transition-colors ml-4 cursor-pointer"
                        aria-label="Закрыть"
                    >&times;</button>
                </div>

                <div class="overflow-x-auto max-h-[85vh]">
                    <table class="w-full bg-brand-light-bg overflow-auto">
                        <thead>
                            <tr class="md:text-2xl text-brand-dark border-b border-brand-border">
                                <th class="pb-2 text-center font-medium a-font">Участник</th>
                                <th class="pb-2 text-center font-medium a-font">Дата рождения</th>
                                <th class="pb-2 text-center font-medium a-font">Разряд</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            @forelse($activeTeamModal['members'] as $member)
                                @php($isCaptain = ($member['role'] ?? null) === 'captain')
                                <tr @class([
                                    'hover:bg-white transition-colors border-b border-brand-border pb-8! md:pb-0!',
                                    'font-bold' => $isCaptain,
                                ])>
                                    <td data-label="Участник" class="py-3">{{ $member['name'] }}</td>
                                    <td data-label="Дата рождения" class="py-3">{{ $member['birthday'] }}</td>
                                    <td data-label="Разряд" class="py-3">{{ $member['rank'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-brand-gray-light">Нет данных об участниках</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
