<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\YachtDocumentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class YachtDocumentTypeSeeder extends Seeder
{
    /**
     * Дефолтные типы документов, синхронизированные с устаревшим enum YachtDocumentType.
     */
    private const DEFAULT_TYPES = [
        [
            'key'             => 'orc_certificate',
            'label'           => 'ORC-сертификат',
            'description'     => 'Сертификат ORC с гоночным баллом и параметрами яхты.',
            'is_configurable' => true,
            'sort_order'      => 10,
        ],
        [
            'key'             => 'ship_ticket',
            'label'           => 'Судовой билет',
            'description'     => 'Судовой билет или свидетельство о регистрации.',
            'is_configurable' => true,
            'sort_order'      => 20,
        ],
        [
            'key'             => 'insurance',
            'label'           => 'Страховка',
            'description'     => 'Действующий страховой полис яхты.',
            'is_configurable' => true,
            'sort_order'      => 30,
        ],
        [
            'key'             => 'regulation',
            'label'           => 'Положение',
            'description'     => 'Положение о соревнованиях.',
            'is_configurable' => true,
            'sort_order'      => 40,
        ],
        [
            'key'             => 'race_instructions',
            'label'           => 'Гоночная инструкция',
            'description'     => 'Инструкция по проведению гонок.',
            'is_configurable' => true,
            'sort_order'      => 50,
        ],
        [
            'key'             => 'charter',
            'label'           => 'Устав',
            'description'     => 'Устав организации или клуба.',
            'is_configurable' => true,
            'sort_order'      => 60,
        ],
        [
            'key'             => 'protocol',
            'label'           => 'Протокол',
            'description'     => 'Протокол результатов соревнований.',
            'is_configurable' => true,
            'sort_order'      => 70,
        ],
        [
            'key'             => 'other',
            'label'           => 'Прочее',
            'description'     => 'Прочие документы, не попадающие в другие категории.',
            'is_configurable' => false,
            'sort_order'      => 100,
        ],
    ];

    public function run(): void
    {
        foreach (self::DEFAULT_TYPES as $type) {
            YachtDocumentType::updateOrCreate(
                ['key' => $type['key']],
                $type,
            );
        }

        YachtDocumentType::flushCache();
    }
}