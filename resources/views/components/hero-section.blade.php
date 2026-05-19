@props(['title' => '', 'desc' => '', 'bgImage' => ''])
<section style="background-image: url({{ $bgImage }})" class="bg-cover relative px-4 md:px-2
            after:absolute after:inset-0 
            after:content-[''] 
            after:bg-linear-to-t 
            after:from-[#2E325C] 
            after:to-transparent">
    <div class="max-w-(--breakpoint-2xl) m-auto text-white pt-80 relative z-40 pb-8">
        <h2 class="a-font text-2xl md:text-6xl">{{ $title }}</h2>
        <p class="max-w-[768px] text-sm md:text-base">{{ $desc }}</p>
    </div>
</section>