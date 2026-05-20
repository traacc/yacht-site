{{-- resources/views/components/button.blade.php --}}
<button {{ $attributes->merge([
    'type' => 'button', 
    'class' => 'inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'
]) }}>
    
    {{-- Проверяем, передана ли иконка в слот icon --}}
    @if(isset($icon))
        <div class="flex-shrink-0 w-5 h-5 inline-flex items-center justify-center">
            {{ $icon }}
        </div>
    @endif

    {{-- Сюда попадает текст кнопки --}}
    <span>{{ $slot }}</span>
    
</button>