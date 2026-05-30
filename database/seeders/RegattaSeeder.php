<?php

namespace Database\Seeders;

use App\Models\Regatta;
use App\Models\Season;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegattaSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'season_year'       => 2026,
                'external_id'       => 1,
                'name'              => 'Волжская регата',
                'level_coefficient' => 1.10,
                'date_start'        => '2026-05-10',
                'date_end'          => '2026-05-12',
                'location'          => 'Нижний Новгород',
                'water_area'        => 'Волга',
                'description'       => 'Трехдневная серия для монокорпов и катамаранов.',
                'race_days_count'   => 3,
                'races_count'       => 4,
                'prizes'            => 'Призы в каждой классовой группе.',
            ],
            [
                'season_year'       => 2026,
                'external_id'       => 2,
                'name'              => 'Московская летняя регата',
                'level_coefficient' => 1.20,
                'date_start'        => '2026-06-15',
                'date_end'          => '2026-06-18',
                'location'          => 'Москва',
                'water_area'        => 'Москва-река',
                'description'       => 'Ежегодная летняя регата для крейсерских яхт.',
                'race_days_count'   => 3,
                'races_count'       => 5,
                'prizes'            => 'Кубки, призы от спонсоров.',
            ],
            [
                'season_year'       => 2026,
                'external_id'       => 3,
                'name'              => 'Сибирская регата',
                'level_coefficient' => 1.30,
                'date_start'        => '2026-07-20',
                'date_end'          => '2026-07-23',
                'location'          => 'Новосибирск',
                'water_area'        => 'Обское водохранилище',
                'description'       => 'Регата для яхт всех классов с разнообразными дистанциями.',
                'race_days_count'   => 3,
                'races_count'       => 5,
                'prizes'            => 'Кубки, призы от спонсоров.',
            ],
            [
                'season_year'       => 2026,
                'external_id'       => 4,
                'name'              => 'Кубок Черного моря',
                'level_coefficient' => 1.40,
                'date_start'        => '2026-08-10',
                'date_end'          => '2026-08-14',
                'location'          => 'Сочи',
                'water_area'        => 'Черное море',
                'description'       => 'Прибрежная регата с вечерними гонками и развлекательной программой.',
                'race_days_count'   => 4,
                'races_count'       => 5,
                'prizes'            => 'Медали, призы от спонсоров, сертификаты.',
            ],
            [
                'season_year'       => 2026,
                'external_id'       => 5,
                'name'              => 'Baltic Challenge',
                'level_coefficient' => 1.50,
                'date_start'        => '2026-08-01',
                'date_end'          => '2026-08-04',
                'location'          => 'Санкт-Петербург',
                'water_area'        => 'Финский залив',
                'description'       => 'Открытая регата с прибрежными и оффшорными этапами.',
                'race_days_count'   => 3,
                'races_count'       => 6,
                'prizes'            => 'Медали, призы от спонсоров, сертификаты.',
            ],
        ];

        foreach ($items as $item) {
            $season = Season::firstWhere('year', $item['season_year']);

            if ($season === null) {
                continue;
            }

            $season->regattas()->updateOrCreate(
                ['name' => $item['name']],
                [
                    'external_id'       => $item['external_id'],
                    'series_id'         => null,
                    'level_coefficient' => $item['level_coefficient'],
                    'date_start'        => $item['date_start'],
                    'date_end'          => $item['date_end'],
                    'location'          => $item['location'],
                    'water_area'        => $item['water_area'],
                    'description'       => $item['description'],
                    'race_days_count'   => $item['race_days_count'],
                    'races_count'       => $item['races_count'],
                    'prizes'            => $item['prizes'],
                ]
            );
        }

        // Синхронизируем счётчик sequences с максимальным external_id
        $maxExternalId = Regatta::max('external_id') ?? 0;
        DB::table('sequences')->updateOrInsert(
            ['name' => 'regattas_external_id'],
            ['current_value' => $maxExternalId]
        );
    }
}
