<?php

declare(strict_types=1);

namespace App\Actions\Service;

use App\Models\CharterYachtModel;

/**
 * Проставляет модели справочника страну базирования.
 *
 * По ТЗ страна берётся от регаты, где модель использовали впервые, поэтому
 * заполняется она один раз и только пустая: дальше это ручное поле — лодку
 * можно перегнать в другую страну, и затирать правку админа автоматикой нельзя.
 */
final class AssignCharterYachtModelCountry
{
    public function handle(?CharterYachtModel $model, ?string $country): void
    {
        if ($model === null) {
            return;
        }

        $country = trim((string) $country);

        if ($country === '' || trim((string) $model->country) !== '') {
            return;
        }

        $model->update(['country' => $country]);
    }
}
