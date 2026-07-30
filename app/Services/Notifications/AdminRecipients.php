<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\Chat\ChatRecipients;
use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Collection;

/**
 * Кому уходят служебные уведомления о событиях на сайте.
 *
 * Права берём из общей матрицы доступа, а не из захардкоженного списка ролей:
 * иначе судья, которому раздел закрыли в настройках доступа, всё равно получал
 * бы уведомления по этому разделу.
 *
 * @see ChatRecipients — та же логика для диалогов чата,
 *      с дополнительной предзагрузкой настроек уведомлений.
 */
class AdminRecipients
{
    /**
     * Сотрудники, которым открыт указанный раздел админ-панели.
     *
     * @param  class-string  $filamentClass  Ресурс или страница админ-панели.
     * @return Collection<int, User>
     */
    public function forSection(string $filamentClass): Collection
    {
        return $this->panelUsers()
            ->filter(static fn (User $user): bool => AccessControl::allows($filamentClass, $user))
            ->values();
    }

    /**
     * Все пользователи с панельной ролью (то есть кроме обычных участников).
     *
     * @return Collection<int, User>
     */
    public function panelUsers(): Collection
    {
        $panelRoles = array_filter(
            SystemRole::cases(),
            static fn (SystemRole $role): bool => $role !== SystemRole::User,
        );

        return User::query()
            ->whereIn('system_role', array_map(static fn (SystemRole $r): string => $r->value, $panelRoles))
            ->get();
    }
}
