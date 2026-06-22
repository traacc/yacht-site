@props(['tabs' => []])
<nav class="flex border-b border-[#EAEAEA] mb-8 mt-8" role="tablist">
    @foreach ($tabs as $key => $tab)
        @php
            $label = is_array($tab) ? ($tab['label'] ?? '') : $tab;
            $url = is_array($tab) ? ($tab['url'] ?? null) : null;
            $active = is_array($tab) && ($tab['active'] ?? false);
        @endphp
        @if ($url)
            <a
                href="{{ $url }}"
                @class([
                    'px-6 py-3 text-lg font-semibold border-b-2 transition-colors duration-200 cursor-pointer',
                    'border-[#2D92CE] text-[#2D92CE]' => $active,
                    'border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6]' => ! $active,
                ])
                role="tab"
                @if ($active) aria-selected="true" @endif
            >
                {{ $label }}
            </a>
        @else
            <button
                type="button"
                @click="activeTab = '{{ $key }}'"
                :class="activeTab === '{{ $key }}'
                    ? 'border-[#2D92CE] text-[#2D92CE]'
                    : 'border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6]'"
                class="px-6 py-3 text-lg font-semibold border-b-2 transition-colors duration-200 cursor-pointer"
                role="tab"
                :aria-selected="activeTab === '{{ $key }}'"
            >
                {{ $label }}
            </button>
        @endif
    @endforeach
</nav>
