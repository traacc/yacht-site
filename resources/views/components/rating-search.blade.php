{{-- Поле поиска по таблице рейтинга. Фильтрация целиком на Alpine:
     данные рейтинга уже отрендерены в странице, запрос к серверу не нужен.
     $model — имя свойства в родительском x-data, куда пишется строка поиска. --}}
@props(['model', 'placeholder' => 'Поиск по имени'])

<div class="relative w-full md:w-72">
    <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[#9FA6AD] pointer-events-none"
         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
    </svg>
    <input
        type="search"
        x-model.debounce.200ms="{{ $model }}"
        class="w-full border-0 bg-[#F8F8F8] focus:outline-hidden py-2 pl-10 pr-10 text-sm md:text-base text-[#2E325C] placeholder:text-[#9FA6AD]"
        placeholder="{{ $placeholder }}"
        aria-label="{{ $placeholder }}"
    >
    <button
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-[#9FA6AD] hover:text-[#2E325C] transition-colors cursor-pointer"
        x-show="{{ $model }}.length > 0"
        @click="{{ $model }} = ''"
        aria-label="Очистить поиск"
        style="display:none"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>
