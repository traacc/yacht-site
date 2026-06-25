@php
    $settings = app(\App\Services\SettingsService::class);

    $bannerEnabled = (bool) $settings->get('home.banner_enabled', false);
    $bannerTitle = $settings->get('home.banner_title');
    $bannerText = $settings->get('home.banner_text');
    $bannerButtonText = $settings->get('home.banner_button_text');
    $bannerButtonUrl = $settings->get('home.banner_button_url');
@endphp

@if ($bannerEnabled && ($bannerTitle || $bannerText))
<div x-data="{
        isOpen: false,
        init() {
            if (!sessionStorage.getItem('capture_window_shown')) {
                setTimeout(() => { this.isOpen = true; }, 10000);
            }
        },
        closeModal() {
            this.isOpen = false;
            sessionStorage.setItem('capture_window_shown', '1');
        }
     }"
     x-show="isOpen"
     style="display: none;"
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true">

    <div
            x-show="isOpen"
            x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity bg-black/50 z-20"
    class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">


        <!-- Само модальное окно -->
        <div x-show="isOpen"
            @click.outside="closeModal()"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="px-6 py-12 relative overflow-hidden transition-all bg-white max-w-[1000px] w-full z-30 top-1/2 left-1/2 -translate-1/2">

            <button @click="closeModal()" class="text-2xl text-[#2E325C] absolute right-5 top-5 font-bold z-30">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>

            <div class="max-w-[562px] relative z-10 mx-auto">
                <div class="mt-3 text-center w-full">
                    @if ($bannerTitle)
                        <h3 class="text-4xl text-[#2E325C] a-font mb-5">
                            {{ $bannerTitle }}
                        </h3>
                    @endif

                    @if ($bannerText)
                        <p class="text-lg text-[#444] mb-5">
                            {!! nl2br(e($bannerText)) !!}
                        </p>
                    @endif

                    @if ($bannerButtonText && $bannerButtonUrl)
                        <a href="{{ $bannerButtonUrl }}"
                           class="inline-block bg-[#2D92CE] hover:bg-[#2E325C] transition-colors text-white text-lg font-medium px-8 py-3 rounded">
                            {{ $bannerButtonText }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif
