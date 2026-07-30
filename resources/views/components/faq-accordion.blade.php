{{--
    Аккордеон вопросов и ответов F.A.Q.

    Используется на странице «Помощь» (с поиском) и на главной (без поиска) —
    до этого разметка была скопирована в двух шаблонах и расходилась между ними.

    @param \Illuminate\Support\Collection<int, \App\Models\Faq> $items
    @param bool $searchable Показывать поле поиска (клиентский фильтр по вопросу и ответу).
--}}
@props(['items', 'searchable' => false])

@if($items->isNotEmpty())
@php
    // Поиск идёт и по вопросу, и по тексту ответа: ответ приходит HTML-ом из RichEditor.
    $haystacks = $items
        ->map(fn ($item) => \Illuminate\Support\Str::lower($item['question'].' '.strip_tags($item['answer'])))
        ->values()
        ->all();
@endphp
<div x-data="{
        open: null,
        q: '',
        haystacks: @js($haystacks),
        matches(index) {
            const q = this.q.trim().toLowerCase();

            return q === '' || this.haystacks[index].includes(q);
        },
        visibleCount() {
            return this.haystacks.filter((_, index) => this.matches(index)).length;
        }
    }">
    @if($searchable)
    <div class="searchbar flex flex-col md:flex-row gap-4 mb-6">
        <input x-model.debounce.200ms="q" class="w-full border-0 py-4 px-4 bg-[#F8F8F8] focus:outline-hidden" type="text" placeholder="Поиск по вопросам">
    </div>

    <p x-show="visibleCount() === 0" x-cloak class="text-[#2E325C]">
        Ничего не найдено. Попробуйте изменить запрос.
    </p>
    @endif

    <div class="divide-y divide-gray-200">
        @foreach($items as $index => $item)
        <div class="py-4" @if($searchable) x-show="matches({{ $index }})" x-cloak @endif>
            <button
                @click="open === {{ $index }} ? open = null : open = {{ $index }}"
                class="flex justify-between items-center w-full text-left gap-4 cursor-pointer border-b pb-5 border-gray-200"
            >
                <span class="text-lg font-semibold text-[#2E325C] pr-4">{{ $item['question'] }}</span>
                <svg
                    class="w-5 h-5 shrink-0 text-[#2D92CE] transition-transform duration-300"
                    :class="open === {{ $index }} ? 'rotate-180' : ''"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div
                x-show="open === {{ $index }}"
                x-collapse
                x-cloak
            >
                <div class="pt-4 text-brand-gray leading-relaxed prose prose-sm max-w-none">
                    {!! $item['answer'] !!}
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@else
<p class="text-[#2E325C]">Вопросы появятся позже.</p>
@endif
