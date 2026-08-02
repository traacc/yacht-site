<?php

declare(strict_types=1);

use App\Enums\AdvertType;
use App\Models\AdvertCategory;
use Illuminate\Database\Migrations\Migration;

/**
 * Типы парусов для биржи парусов.
 *
 * Справочник категорий у досок пустой по умолчанию и заполняется из админки, но
 * без типа паруса биржа не фильтруется, а сам список от заказчика не зависит.
 * Заводим один раз и только те slug'и, которых ещё нет — повторный прогон
 * миграций не должен плодить дубли и затирать правки администратора.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        'grot' => 'Грот',
        'staksel' => 'Стаксель',
        'genoa' => 'Генуя',
        'spinnaker' => 'Спинакер',
        'gennaker' => 'Геннакер',
        'storm' => 'Штормовой парус',
        'other' => 'Прочее',
    ];

    public function up(): void
    {
        $existing = AdvertCategory::query()
            ->withTrashed()
            ->where('type', AdvertType::Sails)
            ->pluck('slug')
            ->all();

        $sort = 0;

        foreach (self::CATEGORIES as $slug => $title) {
            $sort += 10;

            if (in_array($slug, $existing, true)) {
                continue;
            }

            AdvertCategory::create([
                'type' => AdvertType::Sails,
                'title' => $title,
                'slug' => $slug,
                'sort_order' => $sort,
            ]);
        }
    }

    public function down(): void
    {
        AdvertCategory::query()
            ->where('type', AdvertType::Sails)
            ->whereIn('slug', array_keys(self::CATEGORIES))
            ->forceDelete();
    }
};
