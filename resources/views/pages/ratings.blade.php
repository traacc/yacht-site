<x-public-layout>
<x-breadcrumbs_page title="Результаты соревнований">
</x-breadcrumbs_page>
<x-hero-section title="Результаты соревнований"
desc="Итоги сезона Ассоциации CarterPro. Таблицы публикуются от новых соревнований к более ранним."
bgImage="{{ asset('images/bg/results.png') }}"
>
</x-hero-section>

<main class="main">
        <div class="container">
            <div class="grid grid-cols-1 gap-4">

                <div class="bg-brand-light rounded-xl md:p-4 md:pr-0">
                    <h3 class="font-display  text-[#2E325C] text-3xl mb-4 a-font">Командный рейтинг</h3>
                    <div class="overflow-auto md:p-6 md:pt-0 bg-white">
                        <table class="w-full text-sm md:text-base responsive-table">
                            <thead class="sticky bg-white top-0 pt-6">
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] ">
                                    <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Команда</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Очки</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium">
                                <template x-data="{ teams: [['Барс', 7.0], ['Барс', 7.0], ['Барс', 7.0], ['Игорь Скалин', 7.0], ['Игорь Скалин', 7.0], ['Игорь Скалин', 7.0]] }" x-for="(team, i) in teams" :key="i">
                                    <tr>
                                        <td class="py-2" data-label="Место">
                                            <div class="flex items-center md:justify-center gap-3">
                                                <span :class="i===0?'text-[#C2A36B]':i===1?'text-[#9FA6AD]':'text-[#B56A3A]'" class="font-bold text-sm">{!! file_get_contents(public_path('images/icons/cup.svg')) !!}</span><span x-text="i+1"></span>
                                            </div>
                                            
                                        </td>
                                        <td class="py-2" data-label="Участник" x-text="team[0]"></td>
                                        <td class="py-2" data-label="Очки" x-text="team[1]"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                </div>
                <div class="bg-brand-light rounded-xl md:p-4">
                    <h3 class="font-display  text-[#2E325C]  text-3xl mb-4 a-font">Личный рейтинг</h3>
                    <div class="overflow-x-auto md:p-6 md:pt-0 bg-white">
                        <table class="w-full text-sm md:text-base responsive-table">
                            <thead>
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] bg-white sticky top-0">
                                    <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Участник</th>
                                    <th class="pb-2 text-center font-medium a-font pt-6">Очки</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium">
                                <template x-data="{ participants: [['Игорь Скалин', 7.0], ['Игорь Скалин', 7.0], ['Игорь Скалин', 7.0], ['Игорь Скалин', 7.0], ['Игорь Скалин', 7.0], ['Игорь Скалин', 7.0]] }" x-for="(p, i) in participants" :key="i">
                                    <tr>
                                        <td class="py-2" data-label="Место">
                                            <div class="flex items-center md:justify-center gap-3">
                                                <span :class="i===0?'text-[#C2A36B]':i===1?'text-[#9FA6AD]':'text-[#B56A3A]'" class="font-bold text-sm">{!! file_get_contents(public_path('images/icons/cup.svg')) !!}</span><span x-text="i+1"></span>
                                            </div>
                                            
                                        </td>
                                        <td class="py-2" data-label="Участник" x-text="p[0]"></td>
                                        <td class="py-2" data-label="Очки" x-text="p[1]"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

</main>

<x-feedback-section>
</x-feedback-section>
</x-public-layout>
