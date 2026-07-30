@php
    $photos = $advert->photos();
    $isAuthor = auth()->check() && auth()->id() === $advert->user_id;
@endphp
<x-public-layout :title="$advert->title" :description="Str::limit(strip_tags($advert->description), 160)">
<x-breadcrumbs_page :title="$advert->title">
</x-breadcrumbs_page>

<main class="main">
    <section class="py-12 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto">

            <a href="{{ route($advert->type->routeName()) }}" class="text-[#2D92CE] font-semibold hover:underline">
                ← {{ $advert->type->pluralLabel() }}
            </a>

            @if (session('advert_contact_error'))
                <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3">
                    {{ session('advert_contact_error') }}
                </div>
            @endif

            <div class="mt-6 flex flex-col lg:flex-row gap-10">

                {{-- Фотографии --}}
                <div class="lg:w-2/3">
                    @if (count($photos) > 0)
                        <div x-data="{
                            activeIndex: 0,
                            lightboxOpen: false,
                            images: {{ Js::from(array_column($photos, 'src')) }},
                            prevImage() {
                                this.activeIndex = (this.activeIndex - 1 + this.images.length) % this.images.length;
                            },
                            nextImage() {
                                this.activeIndex = (this.activeIndex + 1) % this.images.length;
                            }
                        }">
                            <div class="bg-[#F8F8F8] cursor-pointer" @click="lightboxOpen = true">
                                <img :src="images[activeIndex]" alt="{{ $advert->title }}"
                                     class="w-full max-h-[520px] object-contain">
                            </div>

                            @if (count($photos) > 1)
                                <div class="flex gap-2 overflow-x-auto mt-3 pb-2">
                                    <template x-for="(img, idx) in images" :key="idx">
                                        <div class="cursor-pointer shrink-0 w-20 aspect-square"
                                             :class="activeIndex === idx ? 'ring-2 ring-[#2D92CE]' : ''"
                                             @click="activeIndex = idx">
                                            <img :src="img" class="object-cover h-full w-full" alt="">
                                        </div>
                                    </template>
                                </div>
                            @endif

                            {{-- Лайтбокс --}}
                            <div x-show="lightboxOpen" x-cloak
                                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
                                 @keydown.left.window="prevImage()"
                                 @keydown.right.window="nextImage()"
                                 @keydown.escape.window="lightboxOpen = false"
                                 x-trap.noscroll="lightboxOpen">
                                <div @click.away="lightboxOpen = false" class="relative w-full max-w-[1000px] max-h-[90vh] mx-4">
                                    <button @click="lightboxOpen = false"
                                            class="absolute -top-10 right-0 text-white text-3xl z-50 hover:opacity-70">&times;</button>

                                    <div class="relative flex items-center justify-center">
                                        <button @click="prevImage()" aria-label="Предыдущее"
                                                class="absolute left-2 z-50 p-2 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                        </button>

                                        <img :src="images[activeIndex]" class="w-full max-h-[80vh] object-contain" alt="">

                                        <button @click="nextImage()" aria-label="Следующее"
                                                class="absolute right-2 z-50 p-2 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-[#F8F8F8] h-72 flex items-center justify-center text-brand-gray-light">
                            Фотографий нет
                        </div>
                    @endif

                    <h1 class="section-title a-font text-3xl md:text-4xl mt-8 mb-4">{{ $advert->title }}</h1>

                    <div class="prose max-w-none text-brand-gray font-medium whitespace-pre-line">{{ $advert->description }}</div>
                </div>

                {{-- Цена, контакты, связь --}}
                <div class="lg:w-1/3">
                    <div class="bg-[#F8F8F8] p-6 sticky top-4">
                        @if ($advert->isSold())
                            <div class="bg-[#2E325C] text-white text-sm font-semibold px-3 py-2 mb-4 text-center">
                                Объявление закрыто — товар продан
                            </div>
                        @endif

                        <div class="text-[#2E325C] text-3xl a-font mb-4">{{ $advert->priceLabel() }}</div>

                        <dl class="text-sm space-y-2 mb-6 text-brand-gray">
                            @if ($advert->category)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-brand-gray-light">Категория</dt>
                                    <dd class="font-medium text-right">{{ $advert->category->title }}</dd>
                                </div>
                            @endif
                            @if ($advert->yacht)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-brand-gray-light">Яхта</dt>
                                    <dd class="font-medium text-right">{{ $advert->yacht->name }}</dd>
                                </div>
                            @endif
                            @if ($advert->city)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-brand-gray-light">Город</dt>
                                    <dd class="font-medium text-right">{{ $advert->city }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-4">
                                <dt class="text-brand-gray-light">Автор</dt>
                                <dd class="font-medium text-right">{{ $advert->author?->name ?? '—' }}</dd>
                            </div>
                            @if ($advert->published_at)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-brand-gray-light">Опубликовано</dt>
                                    <dd class="font-medium text-right">{{ $advert->published_at->translatedFormat('j F Y') }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($advert->hasContacts())
                            <div class="border-t border-[#EAEAEA] pt-4 mb-4">
                                <div class="text-sm font-semibold text-[#2E325C] mb-3">Контакты</div>
                                <ul class="space-y-2 text-sm">
                                    @if ($advert->contact_phone)
                                        <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $advert->contact_phone) }}" class="text-[#2D92CE] hover:underline">{{ $advert->contact_phone }}</a></li>
                                    @endif
                                    @if ($advert->contact_telegram)
                                        <li><a href="https://t.me/{{ ltrim($advert->contact_telegram, '@') }}" target="_blank" rel="noopener" class="text-[#2D92CE] hover:underline">{{ $advert->contact_telegram }}</a></li>
                                    @endif
                                    @if ($advert->contact_email)
                                        <li><a href="mailto:{{ $advert->contact_email }}" class="text-[#2D92CE] hover:underline break-all">{{ $advert->contact_email }}</a></li>
                                    @endif
                                </ul>
                            </div>
                        @endif

                        {{-- Автору своего объявления писать некому. --}}
                        @unless ($isAuthor)
                            @guest
                                <button type="button"
                                        @click.prevent="$dispatch('open-login-modal')"
                                        class="w-full bg-[#2D92CE] text-white py-3 px-6 hover:bg-[#0074CC] transition-colors font-semibold">
                                    Написать автору
                                </button>
                                <p class="mt-2 text-xs text-brand-gray-light text-center">
                                    Нужен вход — переписка ведётся в личном кабинете.
                                </p>
                            @else
                                @if ($advert->isPublished())
                                    <form method="POST" action="{{ route('carter30.advert-contact', $advert) }}">
                                        @csrf
                                        <button type="submit"
                                                class="w-full bg-[#2D92CE] text-white py-3 px-6 hover:bg-[#0074CC] transition-colors font-semibold">
                                            Написать автору
                                        </button>
                                    </form>
                                @endif
                            @endguest
                        @else
                            <a href="{{ url('/user/adverts') }}"
                               class="block text-center border border-[#2D92CE] text-[#2D92CE] py-3 px-6 hover:bg-[#2D92CE] hover:text-white transition-colors font-semibold">
                                Это ваше объявление
                            </a>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<x-feedback-section>

</x-feedback-section>
</x-public-layout>
