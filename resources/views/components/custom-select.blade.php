@props([
    'options' => [], // Массив вида ['value' => 'Label']
    'placeholder' => 'Выберите значение'
])

<div 
    x-data="{
        open: false,
        value: @entangle($attributes->wire('model')),
        options: {{ json_encode($options) }},
        emptyOptions: {{ json_encode(array_fill_keys(array_keys($options), '')) }},
        get selectedLabel() {
            return this.options[this.value] || '{{ $placeholder }}';
        },
        select(key) {
            this.value = key;
            this.open = false;
        }
    }"
    @click.away="open = false"
    @keydown.escape.stop="open = false"
    class="relative w-full"
>
    <!-- Кнопка выбора -->
    <button 
        type="button"
        @click="open = !open"
        aria-haspopup="listbox"
        :aria-expanded="open"
        {{ $attributes->merge(['class' => 'relative w-full cursor-pointer bg-white py-2 pl-3 pr-8 text-left font-semibold text-[#2E325C] border border-[#2D92CE] text-sm md:text-base transition duration-150 ease-in-out']) }}
    >
        <span 
            class="block truncate text-gray-900" 
            :class="{ 'text-[#2D92CE]': !value }"
            x-text="selectedLabel"
        ></span>
        <span class="pointer-events-none absolute inset-y-0 pr-2 right-0 flex items-center">
            <svg class="h-3 w-3 text-transparent transition-transform duration-200 rotate-180" :class="{ 'rotate-360': open }" viewBox="0 0 14 8" fill="currentColor" aria-hidden="true">
                <path d="M13 7L7 0.999999L1 7" stroke="#2D92CE" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </button>

    <!-- Выпадающий список -->
    <ul 
        x-show="open"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute z-10 mt-4 max-h-60 w-full overflow-auto bg-white py-1 md:text-base text-sm"
        tabindex="-1"
        role="listbox"
        style="display: none;"
    >
        <template x-for="(label, key) in options" :key="key">
            <li 
                @click="select(key)"
                class="group relative cursor-default select-none py-2 pl-3 pr-9 text-gray-900 hover:bg-[#2D92CE] hover:text-white transition-colors duration-100 ease-in-out"
                role="option"
                :aria-selected="value == key"
            >
                <span 
                    x-text="label"
                    class="block truncate"
                    :class="{ 'font-semibold': value == key, 'font-normal': value != key }"
                ></span>

                <!-- Галочка для выбранного элемента -->
                <span 
                    x-show="value == key"
                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-[#2D92CE] group-hover:text-white"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                    </svg>
                </span>
            </li>
        </template>
    </ul>
</div>