{{--
    Имя пользователя как ссылка, открывающая карточку (livewire:user-card-modal).
    Работает внутри Livewire-компонента (использует wire:click="$dispatch(...)").
    Если id не передан — выводит просто текст (или «—»).

    Использование: <x-user-name-link :id="$captain['id']" :name="$captain['name']" />
--}}
@props(['id' => null, 'name' => null, 'fallback' => '—'])

@if($id && $name)
    <button
        type="button"
        wire:click="$dispatch('open-user-card', { userId: '{{ $id }}' })"
        {{ $attributes->merge(['class' => 'text-[#2D92CE] hover:underline cursor-pointer']) }}
    >{{ $name }}</button>
@else
    {{ $name ?: $fallback }}
@endif
