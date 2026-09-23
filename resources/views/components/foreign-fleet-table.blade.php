@props([
    // Collection<App\Models\ForeignRegattaYacht>
    'yachts',
    // Имя window-события общей формы заявки; null — заявки регата не принимает.
    'requestEvent' => null,
])

{{--
    Флот дивизиона таблицей: модель, название, состояние и кнопки вариантов цен.

    Карточками хорош небольшой флот, а полтора десятка разных лодок читаются
    только списком — по ТЗ посетитель сравнивает лодки и цены глазами и жмёт
    кнопку нужного варианта прямо в строке.

    Подробности лодки (описание, галерея, полный набор цен) открываются той же
    модалкой, что и с карточки (@see components/foreign-yacht-details).

    На узком экране таблица раскладывается в стопку (`block md:table-cell`):
    горизонтальная прокрутка увела бы кнопки заявки за край, а именно они здесь
    главное. Разметка при этом одна — дублировать строки карточками нельзя,
    иначе у каждой лодки окажется по две модалки.
--}}

<div {{ $attributes->merge(['class' => 'overflow-x-auto border border-[#C6C6C6]']) }}>
    <table class="w-full text-sm md:min-w-[720px]">
        <thead class="hidden md:table-header-group">
            <tr class="bg-[#F8F8F8] text-[#2E325C] text-left">
                <th class="p-3 font-semibold">Модель</th>
                <th class="p-3 font-semibold">Название</th>
                <th class="p-3 font-semibold">Характеристики</th>
                <th class="p-3 font-semibold">Экипаж</th>
                <th class="p-3 font-semibold">Варианты</th>
            </tr>
        </thead>

        <tbody class="block md:table-row-group">
            @foreach ($yachts as $yacht)
                @php
                    $photos = $yacht->effectivePhotos();
                    $description = trim((string) $yacht->effectiveDescription());
                    $hasDetails = $description !== '' || count($photos) > 0;

                    $specs = array_values(array_filter([
                        $yacht->cabinsLabel(),
                        $yacht->effectiveYear() ? $yacht->effectiveYear().' г.' : null,
                        $yacht->effectiveDownwindSail()?->label(),
                    ]));

                    $offers = $requestEvent === null ? [] : $yacht->offeredParticipations();
                @endphp

                <tr x-data="{ details: false }"
                    class="block md:table-row border-t border-[#EAEAEA] p-4 md:p-0 md:align-top">
                    <td class="block md:table-cell md:p-3 text-[#2E325C] font-semibold">{{ $yacht->effectiveModel() ?: '—' }}</td>

                    <td class="block md:table-cell md:p-3">
                        @if ($yacht->name)
                            <div class="text-[#2E325C]">{{ $yacht->name }}</div>
                        @endif
                        @if ($hasDetails)
                            <button type="button" @click="details = true"
                                    class="text-[#2D92CE] font-semibold text-xs hover:underline mt-1">Подробнее</button>
                        @endif
                    </td>

                    <td class="block md:table-cell md:p-3 text-brand-gray-light">
                        {{ count($specs) > 0 ? implode(' · ', $specs) : '—' }}
                    </td>

                    <td class="block md:table-cell md:p-3 text-brand-gray-light">
                        @if ($yacht->hasSkipper())
                            <div class="text-[#2E325C]">Шкипер — {{ $yacht->skipper_name }}</div>
                        @else
                            <div>Без шкипера</div>
                        @endif

                        @if ($yacht->sellsSeats() || $yacht->sellsCabins())
                            <div>{{ ucfirst($yacht->freeSeatsLabel()) }}</div>
                        @else
                            {{-- Места могут быть свободны, но без цены не продаются. --}}
                            <div>Мест в продаже нет</div>
                        @endif

                        {{-- Занятость — только про лодку целиком: места у неё
                             могут продаваться и дальше. --}}
                        @if (! $yacht->isAvailable())
                            <div>Целиком — {{ mb_strtolower($yacht->status->label()) }}</div>
                        @endif
                    </td>

                    <td class="block md:table-cell md:p-3 mt-3 md:mt-0">
                        @if (count($offers) > 0)
                            <x-foreign-yacht-cta :yacht="$yacht" :request-event="$requestEvent" compact />
                        @else
                            <span class="text-brand-gray-light hidden md:inline">—</span>
                        @endif

                        <x-foreign-yacht-details :yacht="$yacht" :request-event="$requestEvent" />
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
