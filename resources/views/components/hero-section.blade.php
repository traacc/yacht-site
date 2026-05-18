@props(['title' => '', 'desc' => '', 'bgImage' => ''])
<section style="background-image: url({{ $bgImage }})" class="feedback-form bg-cover relative 
            after:absolute after:inset-0 
            after:content-[''] 
            after:bg-linear-to-t 
            after:from-[#2E325C] 
            after:to-transparent">
    <div class="max-w-(--breakpoint-2xl) m-auto text-white pt-80 relative z-40 pb-8">
        <h2 class="a-font text-6xl">{{ $title }}</h2>
        <p class="max-w-[768px]">{{ $desc }}</p>
    </div>
</section>