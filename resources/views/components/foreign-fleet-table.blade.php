@props([
    // Collection<App\Models\ForeignRegattaYacht>
    'yachts',
    // Имя window-события общей формы заявки; null — заявки регата не принимает.
    'requestEvent' => null,
])

{{--
    Флот дивизиона таблицей — по образцу «Данные яхт для публикации»: модель,
    название, год, каюты, шкипер, база, цена и сопутствующие платежи, занятость.

    Цена печатается только у того, что ещё можно взять: у свободной лодки —
    чартер целиком, у лодки, где остались места, — цены мест. У занятой цены нет.

    Занятость у свободной лодки и у лодки с местами кликабельна: открывает
    карточку лодки (@see components/foreign-yacht-details) с описанием,
    галереей и кнопками заявки. Отдельной колонки кнопок нет — строка и так
    широкая, а выбор варианта живёт в карточке.

    На узком экране строки раскладываются в стопку с подписями у значений:
    горизонтальная прокрутка на 13 колонок уводила бы занятость за край.
--}}
@php
    $cell = 'flex justify-between gap-4 py-0.5 lg:table-cell lg:p-3 lg:align-top';
    $label = 'lg:hidden text-brand-gray-light';
@endphp

<div {{ $attributes->merge(['class' => 'overflow-x-auto border border-[#C6C6C6]']) }}>
    <table class="w-full text-sm">
        <thead class="hidden lg:table-header-group">
            <tr class="bg-[#F8F8F8] text-[#2E325C] text-left align-bottom">
                <th class="p-3 font-semibold">№</th>
                <th class="p-3 font-semibold">Модель</th>
                <th class="p-3 font-semibold">Название</th>
                <th class="p-3 font-semibold">Год</th>
                <th class="p-3 font-semibold">Кают</th>
                <th class="p-3 font-semibold">Шкипер</th>
                <th class="p-3 font-semibold">База</th>
                <th class="p-3 font-semibold">Цена</th>
                <th class="p-3 font-semibold" x-data="{ hint: false }">
                    <span class="relative inline-flex items-center gap-1">
                        Сборы чартерной
                        <button type="button" @click="hint = ! hint" @click.outside="hint = false"
                                class="inline-flex items-center justify-center w-4 h-4 rounded-full border border-[#2D92CE] text-[#2D92CE] text-[10px] leading-none"
                                aria-label="Что входит в сборы">?</button>
                        <span x-show="hint" x-cloak
                              class="absolute top-full left-0 mt-1 z-10 w-48 bg-white border border-[#C6C6C6] p-2 text-xs font-normal text-brand-gray shadow">
                            Уборка, transit log, страховка на регату (race insurance).
                        </span>
                    </span>
                </th>
                <th class="p-3 font-semibold">Депозит за яхту</th>
                <th class="p-3 font-semibold">Аренда спинакера/ геннакера</th>
                <th class="p-3 font-semibold">Депозит за спинакер/ геннакер</th>
                <th class="p-3 font-semibold">Занятость</th>
            </tr>
        </thead>

        <tbody class="block lg:table-row-group">
            @foreach ($yachts as $yacht)
                @php
                    $availability = $yacht->availability();
                    $sail = $yacht->effectiveDownwindSail();

                    // Цена только у того, что ещё можно взять.
                    $prices = match ($availability) {
                        \App\Enums\FleetYachtAvailability::Free => array_filter([$yacht->priceLabel()]),
                        \App\Enums\FleetYachtAvailability::SeatsLeft => collect(\App\Enums\ParticipationOption::cases())
                            ->filter(fn ($option) => $option->isSeatLike() && $yacht->hasVacancy($option))
                            ->map(fn ($option) => ($price = $yacht->participationPriceLabel($option))
                                ? $option->shortLabel().' — '.$price
                                : null)
                            ->filter()
                            ->all(),
                        default => [],
                    };

                    $badge = match ($availability) {
                        \App\Enums\FleetYachtAvailability::Free => 'bg-[#2D92CE] text-white hover:bg-[#0074CC]',
                        \App\Enums\FleetYachtAvailability::SeatsLeft => 'border border-[#2D92CE] text-[#2D92CE] hover:bg-[#2D92CE] hover:text-white',
                        \App\Enums\FleetYachtAvailability::Taken => 'bg-gray-200 text-brand-gray-light',
                    };
                @endphp

                <tr x-data="{ details: false }"
                    class="block lg:table-row border-t border-[#EAEAEA] first:border-t-0 lg:first:border-t p-4 lg:p-0">
                    <td class="hidden lg:table-cell lg:p-3 lg:align-top text-brand-gray-light">{{ $loop->iteration }}</td>

                    {{-- На телефоне модель с названием — заголовок строки. --}}
                    <td class="block lg:table-cell lg:p-3 lg:align-top text-[#2E325C] font-semibold text-base lg:text-sm">
                        <span class="lg:hidden text-brand-gray-light font-normal">{{ $loop->iteration }}.</span>
                        {{ $yacht->effectiveModel() ?: '—' }}
                        @if ($yacht->name)
                            <span class="lg:hidden font-normal">«{{ $yacht->name }}»</span>
                        @endif
                    </td>

                    <td class="hidden lg:table-cell lg:p-3 lg:align-top text-[#2E325C]">{{ $yacht->name ?: '—' }}</td>

                    <td class="{{ $cell }}"><span class="{{ $label }}">Год</span>{{ $yacht->effectiveYear() ?? '—' }}</td>

                    <td class="{{ $cell }}"><span class="{{ $label }}">Кают</span>{{ $yacht->effectiveCabins() ?? '—' }}</td>

                    <td class="{{ $cell }}">
                        <span class="{{ $label }}">Шкипер</span>
                        @if ($yacht->hasSkipper())
                            <span class="font-bold text-[#2E325C]">{{ $yacht->skipper_name }}</span>
                        @else
                            <span class="text-brand-gray-light">—</span>
                        @endif
                    </td>

                    <td class="{{ $cell }}"><span class="{{ $label }}">База</span>{{ $yacht->effectiveBaseMarina() ?: '—' }}</td>

                    <td class="{{ $cell }}">
                        <span class="{{ $label }}">Цена</span>
                        @if (count($prices) > 0)
                            <span class="text-right lg:text-left">
                                @foreach ($prices as $price)
                                    <span class="block text-[#2E325C] font-semibold whitespace-nowrap">{{ $price }}</span>
                                @endforeach
                            </span>
                        @else
                            <span class="text-brand-gray-light">—</span>
                        @endif
                    </td>

                    <td class="{{ $cell }}">
                        <span class="{{ $label }}">Сборы чартерной <span class="text-xs">(уборка, transit log, race insurance)</span></span>
                        <span class="whitespace-nowrap">{{ $yacht->charterFeeLabel() ?? '—' }}</span>
                    </td>

                    <td class="{{ $cell }}"><span class="{{ $label }}">Депозит за яхту</span><span class="whitespace-nowrap">{{ $yacht->depositLabel() ?? '—' }}</span></td>

                    <td class="{{ $cell }}">
                        <span class="{{ $label }}">Аренда спинакера/геннакера</span>
                        <span class="text-right lg:text-left">
                            @if ($sail === \App\Enums\DownwindSail::None)
                                <span class="text-brand-gray-light">нет паруса</span>
                            @else
                                <span class="block whitespace-nowrap">{{ $yacht->downwindSailPriceLabel() ?? '—' }}</span>
                                @if ($sail)
                                    <span class="block text-xs text-brand-gray-light">{{ $sail->label() }}</span>
                                @endif
                            @endif
                        </span>
                    </td>

                    <td class="{{ $cell }}">
                        <span class="{{ $label }}">Депозит за спинакер/геннакер</span>
                        <span class="whitespace-nowrap">{{ $sail === \App\Enums\DownwindSail::None ? '—' : ($yacht->downwindSailDepositLabel() ?? '—') }}</span>
                    </td>

                    <td class="block lg:table-cell lg:p-3 lg:align-top mt-3 lg:mt-0">
                        @if ($availability->opensDetails())
                            <button type="button" @click="details = true"
                                    class="inline-block px-3 py-1.5 text-xs font-semibold uppercase tracking-wide whitespace-nowrap transition-colors {{ $badge }}">
                                {{ $availability->label() }}
                            </button>
                        @else
                            <span class="inline-block px-3 py-1.5 text-xs font-semibold uppercase tracking-wide whitespace-nowrap {{ $badge }}">
                                {{ $availability->label() }}
                            </span>
                        @endif

                        {{-- Карточка есть только у того, что можно взять. --}}
                        @if ($availability->opensDetails())
                            <x-foreign-yacht-details :yacht="$yacht" :request-event="$requestEvent" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
