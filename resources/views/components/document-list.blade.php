@props(['documents'])

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    @foreach($documents as $document)
    <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow p-4">
        <div class="max-w-10 md:max-w-16 shrink-0">
            <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
        </div>
        <div>
            <div class="text-[#2E325C] text-sm md:text-lg font-semibold mb-4">{{ $document['title'] }}</div>
            @if($document['desc'] !== '')
            <div class="text-brand-gray-light font-medium mb-4 text-xs md:text-base">{{ $document['desc'] }}</div>
            @endif
            @if($document['file_url'])
            <a href="{{ $document['file_url'] }}" target="_blank" rel="noopener" class="text-[#2E325C] text-sm md:text-lg font-semibold flex gap-4 items-center">
                <img src="{{ asset('images/icons/download.svg') }}" alt="">
                <span>Скачать PDF</span>
            </a>
            @endif
        </div>
    </div>
    @endforeach
</div>
