<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\User;

/**
 * Жёсткое ограничение финансового контура: только администратор и бухгалтер.
 *
 * Намеренно в обход настраиваемой матрицы прав (RestrictsAccessByRole):
 * там не настроенный пункт считается разрешённым, и судья с секретарём
 * получили бы доступ к платежам по умолчанию.
 *
 * Классы с этим трейтом обязаны быть в @see \App\Support\AccessControl::excluded()
 */
trait RestrictsToPaymentRoles
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->system_role->canManagePayments();
    }
}
