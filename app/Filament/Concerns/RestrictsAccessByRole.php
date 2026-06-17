<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\AccessControl;

/**
 * Подключается к ресурсам и страницам админ-панели, чтобы доступ к ним
 * определялся матрицей прав по системной роли.
 *
 * Filament вызывает canAccess() как при регистрации пункта навигации,
 * так и при обращении к маршруту — поэтому одна точка закрывает и меню,
 * и прямой доступ по URL.
 *
 * @see \App\Support\AccessControl
 * @see \App\Filament\Pages\AccessControlSettings
 */
trait RestrictsAccessByRole
{
    public static function canAccess(): bool
    {
        return AccessControl::allows(static::class);
    }
}
