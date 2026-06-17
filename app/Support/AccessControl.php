<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\SettingsService;
use Filament\Facades\Filament;
use UnitEnum;

/**
 * Централизованное управление доступом к ресурсам и страницам админ-панели
 * по системной роли пользователя (system_role).
 *
 * Матрица прав хранится в настройках (Setting) в виде:
 *   [ roleValue => [ classKey => bool, ... ], ... ]
 *
 * Роль «Администратор» всегда имеет полный доступ и не настраивается.
 * Для остальных панельных ролей не настроенный пункт по умолчанию разрешён,
 * чтобы введение системы не отбирало уже имевшийся доступ.
 *
 * Настраивается через @see \App\Filament\Pages\AccessControlSettings
 */
final class AccessControl
{
    public const SETTING_KEY = 'access.permissions';

    public const SETTING_GROUP = 'access';

    /**
     * Роли, доступ которых настраивается в матрице.
     * Admin не входит — у него всегда полный доступ.
     *
     * @return list<SystemRole>
     */
    public static function configurableRoles(): array
    {
        return [SystemRole::Judge, SystemRole::Secretary, SystemRole::Accountant];
    }

    /**
     * Классы, которые не отображаются в матрице и не подчиняются ей
     * (сама страница управления, профиль, дашборд, страницы только для админа).
     *
     * @return list<class-string>
     */
    public static function excluded(): array
    {
        return [
            \App\Filament\Pages\AccessControlSettings::class,
            \App\Filament\Pages\EditProfile::class,
            \Filament\Pages\Dashboard::class,
            // Имеет собственное ограничение «только администратор».
            \App\Filament\Pages\YachtDocumentSettings::class,
        ];
    }

    /**
     * Стабильный, безопасный для имени поля ключ из FQN класса.
     */
    public static function keyFor(string $class): string
    {
        return str_replace('\\', '_', ltrim($class, '\\'));
    }

    /**
     * Текущая матрица прав.
     *
     * @return array<string, array<string, bool>>
     */
    public static function matrix(): array
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        return (array) $settings->get(self::SETTING_KEY, []);
    }

    /**
     * Разрешён ли пользователю доступ к ресурсу/странице админ-панели.
     */
    public static function allows(string $class, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $role = $user->system_role;

        // Администратор — всегда полный доступ.
        if ($role === SystemRole::Admin) {
            return true;
        }

        // Матрицей управляются только панельные роли (judge/secretary/accountant).
        if (! in_array($role, self::configurableRoles(), true)) {
            return false;
        }

        $matrix = self::matrix();

        // Не настроенный пункт по умолчанию разрешён.
        return (bool) ($matrix[$role->value][self::keyFor($class)] ?? true);
    }

    /**
     * Список настраиваемых ресурсов и страниц админ-панели,
     * сгруппированный по разделу навигации.
     *
     * @return array<string, list<array{class: class-string, key: string, label: string}>>
     */
    public static function manageableItems(): array
    {
        $panel = Filament::getPanel('admin');

        $classes = array_merge($panel->getResources(), $panel->getPages());
        $excluded = self::excluded();

        $grouped = [];

        foreach ($classes as $class) {
            if (in_array($class, $excluded, true)) {
                continue;
            }

            $group = self::groupLabel($class::getNavigationGroup());

            $grouped[$group][] = [
                'class' => $class,
                'key'   => self::keyFor($class),
                'label' => $class::getNavigationLabel(),
            ];
        }

        // Сортируем разделы и пункты внутри них по алфавиту.
        ksort($grouped);

        foreach ($grouped as &$items) {
            usort($items, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
        }
        unset($items);

        return $grouped;
    }

    private static function groupLabel(string|UnitEnum|null $group): string
    {
        if ($group instanceof UnitEnum) {
            return $group->name;
        }

        return $group !== null && $group !== '' ? $group : 'Прочее';
    }
}
