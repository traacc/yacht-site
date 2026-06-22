<x-public-layout title="Результаты серий — командные итоги по сериям регат"
    description="Итоговые командные таблицы по каждой серии регат: очки за каждую регату и общий результат серии.">
<x-breadcrumbs_page title="Результаты серий">
</x-breadcrumbs_page>
<x-hero-section title="Результаты серий"
    desc="Командные итоги по каждой серии регат: очки за каждую регату серии и общий результат."
    bgImage="{{ asset('images/bg/results.webp') }}"
>
</x-hero-section>

<div class="container mx-auto py-10">
    <x-ratings-tabs :tabs="[
        'team' => ['label' => 'Командный рейтинг', 'url' => route('ratings')],
        'personal' => ['label' => 'Личный рейтинг', 'url' => route('ratings') . '#personal'],
        'series' => ['label' => 'Результаты серий', 'url' => route('series-results'), 'active' => true],
        'entries' => ['label' => 'Заявки', 'url' => route('regatta-entries')],
    ]" />

    @forelse($series as $serie)
        @php $regattas = $serie['standings']['regattas']; @endphp
        <section class="mb-12 teams">
            <div class="lg:p-6 bg-brand-light-bg">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <div>
                        <h2 class="section-title a-font">{{ $serie['name'] }}</h2>
                        @if($serie['description'])
                            <p class="text-brand-gray-light mt-1">{{ $serie['description'] }}</p>
                        @endif
                    </div>
                    @if($serie['season'])
                        <span class="text-brand-dark text-lg font-semibold">Сезон {{ $serie['season'] }}</span>
                    @endif
                </div>

                <div class="overflow-x-auto p-3 md:p-6 bg-white">
                    <table class="w-full text-sm md:text-base">
                        <thead>
                            <tr class="text-lg md:text-2xl text-brand-dark border-b border-brand-border">
                                <th class="pb-2 text-center font-medium w-10 md:w-16 a-font">Место</th>
                                <th class="pb-2 text-left font-medium a-font">Команда</th>
                                @foreach($regattas as $regatta)
                                    <th class="pb-2 px-3 text-center font-medium a-font whitespace-nowrap">
                                        <a href="{{ route('competition-details', $regatta['external_id']) }}"
                                           class="hover:text-brand-blue hover:underline">
                                            {{ $regatta['name'] }}
                                        </a>
                                        @if($regatta['date'])
                                            <div class="text-xs text-brand-gray-light font-normal">{{ $regatta['date'] }}</div>
                                        @endif
                                    </th>
                                @endforeach
                                <th class="pb-2 px-3 text-center font-medium a-font">Итого</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y font-medium">
                            @foreach($serie['standings']['standings'] as $row)
                                <tr class="border-b border-brand-border">
                                    <td class="py-3 text-center">{{ $row['rank'] }}</td>
                                    <td class="py-3 text-left text-brand-dark">{{ $row['name'] }}</td>
                                    @foreach($regattas as $regatta)
                                        <td class="py-3 px-3 text-center">
                                            {{ $row['points'][$regatta['id']] !== null ? $row['points'][$regatta['id']] : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="py-3 px-3 text-center font-bold text-brand-blue">{{ $row['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @empty
        <p class="text-center text-brand-gray-light py-20 text-lg">Результаты серий пока не опубликованы.</p>
    @endforelse
</div>

<x-feedback-section></x-feedback-section>
</x-public-layout>
