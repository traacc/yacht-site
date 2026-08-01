{{--
    Блок «Видео» внутри текста (App\Filament\RichEditor\CustomBlocks\VideoBlock).

    Разметка повторяет плееры туров и кейсов ремонта. <iframe> переживает
    Str::sanitizeHtml() только потому, что элемент разрешён в AppServiceProvider —
    без этого блок молча превратился бы в пустоту.
--}}
@php
    $frameClass = match ($ratio) {
        '4:3' => 'aspect-[4/3]',
        '9:16' => 'aspect-[9/16] max-w-[420px] mx-auto',
        default => 'aspect-video',
    };
@endphp

<figure class="rich-video not-prose my-8">
    <div class="{{ $frameClass }} bg-black">
        <iframe src="{{ $embedUrl }}"
                class="w-full h-full"
                title="{{ $caption ?? 'Видео' }}"
                loading="lazy"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen></iframe>
    </div>

    @if ($caption)
        <figcaption class="text-sm text-brand-gray-light mt-2">{{ $caption }}</figcaption>
    @endif
</figure>
