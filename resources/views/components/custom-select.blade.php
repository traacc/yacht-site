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
        class="relative w-full cursor-default rounded-md bg-white py-2 pl-3 pr-10 text-left text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6 transition duration-150 ease-in-out"
    >
        <span 
            class="block truncate text-gray-900" 
            :class="{ 'text-gray-400': !value }"
            x-text="selectedLabel"
        ></span>
        <span class="pointer-events-none absolute inset-y-0 pr-2 right-0 flex items-center">
            <svg class="h-5 w-5 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
            </svg>
        </span>
    </button>

    <!-- Выпадающий список -->
    <ul 
        x-show="open"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
        tabindex="-1"
        role="listbox"
        style="display: none;"
    >
        <template x-for="(label, key) in options" :key="key">
            <li 
                @click="select(key)"
                class="group relative cursor-default select-none py-2 pl-3 pr-9 text-gray-900 hover:bg-indigo-600 hover:text-white transition-colors duration-100 ease-in-out"
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
                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600 group-hover:text-white"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                    </svg>
                </span>
            </li>
        </template>
    </ul>
</div>