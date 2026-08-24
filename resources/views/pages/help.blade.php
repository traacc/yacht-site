<x-public-layout title="Помощь CarterPro - актуальные вопросы и их решения" description="Готовые инструкции проверки электросистем перед регатой, ответы на частозадаваемые вопросы и помощь новым участникам CarterPro">
<x-breadcrumbs_page title="Помощь">
</x-breadcrumbs_page>
{{--
    Ключи табов: guide — «Помощь по сайту», users — F.A.Q., owners — справочник специалистов.
    Ключ `users` исторический: на него ссылается якорь /help#users в шапке сайта
    и внешние ссылки, поэтому переименованию не подлежит.
--}}
<main x-data="{
    activeTab: { '#users': 'users', '#owners': 'owners' }[window.location.hash] ?? 'guide',
}"
    x-on:switch-help-tab.window="activeTab = $event.detail.tab"
    class="main">
    <section class="md:py-12 py-4 reggata-list">
        <div class="container mx-auto">
            <h2 class="section-title a-font text-5xl">Помощь</h2>
        </div>
    </section>

    {{-- Табы --}}
    <section class="container mx-auto mb-8">
        <div class="flex flex-wrap gap-2 border-b border-gray-200">
            <button @click="activeTab = 'guide'"
                    :class="activeTab === 'guide' ? 'text-[#2D92CE] border-b-2 border-[#2D92CE]' : 'text-[#2E325C] border-b-2 border-transparent hover:text-[#2D92CE]'"
                    class="px-4 py-3 text-lg font-semibold transition-colors cursor-pointer -mb-px">
                Помощь по сайту
            </button>
            <button @click="activeTab = 'users'"
                    :class="activeTab === 'users' ? 'text-[#2D92CE] border-b-2 border-[#2D92CE]' : 'text-[#2E325C] border-b-2 border-transparent hover:text-[#2D92CE]'"
                    class="px-4 py-3 text-lg font-semibold transition-colors cursor-pointer -mb-px">
                F.A.Q.
            </button>
            <button @click="activeTab = 'owners'"
                    :class="activeTab === 'owners' ? 'text-[#2D92CE] border-b-2 border-[#2D92CE]' : 'text-[#2E325C] border-b-2 border-transparent hover:text-[#2D92CE]'"
                    class="px-4 py-3 text-lg font-semibold transition-colors cursor-pointer -mb-px">
                Для владельцев яхт
            </button>
        </div>
    </section>

    {{-- ===== Таб: Помощь по сайту ===== --}}
    <div x-show="activeTab === 'guide'" x-cloak>
        <section class="container mx-auto pb-12">
            @if(trim(strip_tags($siteGuide)) !== '' || str_contains($siteGuide, '<img'))
            <div class="px-3 prose max-w-none">{!! $siteGuide !!}</div>
            @elseif($siteGuideDocuments === [])
            <p class="px-3 text-[#2E325C]">Раздел заполняется.</p>
            @endif

            @if($siteGuideDocuments !== [])
            <div class="px-3 mt-10">
                <h3 class="text-2xl font-semibold text-[#2E325C] mb-6">Документы</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($siteGuideDocuments as $document)
                    <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow p-4">
                        <div class="max-w-10 md:max-w-16 shrink-0">
                            <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
                        </div>
                        <div>
                            <div class="text-[#2E325C] text-sm md:text-lg font-semibold mb-4">{{ $document['title'] }}</div>
                            @if($document['desc'] !== '')
                            <div class="text-brand-gray-light font-medium mb-4 text-xs md:text-base">{{ $document['desc'] }}</div>
                            @endif
                            <a href="{{ $document['file_url'] }}" target="_blank" rel="noopener" class="text-[#2E325C] text-sm md:text-lg font-semibold flex gap-4 items-center">
                                <img src="{{ asset('images/icons/download.svg') }}" alt="">
                                <span>Открыть PDF</span>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Не нашли ответ — открываем плавающий чат из общего layout. --}}
            <div class="px-3 mt-10 border-t border-gray-200 pt-8">
                <h3 class="text-2xl font-semibold text-[#2E325C]">Остались вопросы по сайту?</h3>
                <p class="mt-2 text-brand-gray">Напишите в службу поддержки — ответим в чате на сайте.</p>

                <button
                    type="button"
                    @click="Livewire.dispatch('open-support-chat', { context: 'site-help' })"
                    class="mt-4 bg-[#2D92CE] text-white py-2 px-6 hover:bg-[#0074CC] transition-colors text-lg font-semibold cursor-pointer"
                >
                    Написать в поддержку
                </button>
            </div>
        </section>
    </div>
    {{-- ===== /Таб: Помощь по сайту ===== --}}

    {{-- ===== Таб: Для владельцев яхт ===== --}}
    <div x-show="activeTab === 'owners'" x-cloak>
        @include('partials.help-directory')
    </div>
    {{-- ===== /Таб: Для владельцев яхт ===== --}}

    {{-- ===== Таб: Для пользователей (FAQ) ===== --}}
    <div x-show="activeTab === 'users'" x-cloak>
        <section class="container mx-auto pb-12">
            <div class="px-3">
                <x-faq-accordion :items="$faq" :searchable="true" />
            </div>

            {{--
                Не нашли ответ — два пути: письменный вопрос администрации
                (модалка из общего layout, ответ приходит в «Мои вопросы»)
                и живой чат поддержки (плавающий виджет из общего layout).
            --}}
            <div class="px-3 mt-10 border-t border-gray-200 pt-8">
                <h3 class="text-2xl font-semibold text-[#2E325C]">Не нашли ответ?</h3>
                <p class="mt-2 text-brand-gray">Задайте вопрос администрации или напишите в службу поддержки — ответим в чате на сайте.</p>

                <div class="mt-4 flex flex-wrap gap-4">
                    <button
                        type="button"
                        @click="isQuestionModalOpen = true"
                        class="bg-[#2D92CE] text-white py-2 px-6 hover:bg-[#0074CC] transition-colors text-lg font-semibold cursor-pointer"
                    >
                        Задать вопрос
                    </button>

                    <button
                        type="button"
                        @click="Livewire.dispatch('open-support-chat', { context: 'faq' })"
                        class="border border-[#2D92CE] text-[#2D92CE] py-2 px-6 hover:bg-[#2D92CE] hover:text-white transition-colors text-lg font-semibold cursor-pointer"
                    >
                        Написать в поддержку
                    </button>
                </div>
            </div>
        </section>
    </div>
    {{-- ===== /Таб: Для пользователей (FAQ) ===== --}}
</main>



<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>
