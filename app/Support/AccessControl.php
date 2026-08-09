<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SystemRole;
use App\Filament\Concerns\ScopesToOwnedRegattas;
use App\Filament\Pages\AccessControlSettings;
use App\Filament\Pages\AiNewsSettings;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\YachtDocumentSettings;
use App\Filament\Resources\ArchivedRegattaEntries\ArchivedRegattaEntryResource;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Filament\Resources\News\NewsResource;
use App\Filament\Resources\PaymentRegistries\PaymentRegistryResource;
use App\Filament\Resources\PaymentRegistryLogs\PaymentRegistryLogResource;
use App\Filament\Resources\PendingRegattaEntries\PendingRegattaEntryResource;
use App\Filament\Resources\RaceResults\RaceResultResource;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Filament\Resources\RegattaEntryDocumentTypeResource;
use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Filament\Resources\Regattas\RegattaResource;
use App\Filament\Resources\TeamRatings\TeamRatingResource;
use App\Filament\Resources\Teams\TeamResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Yachts\YachtResource;
use App\Models\User;
use App\Services\SettingsService;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
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
     * Разделы админки, доступные роли «Админ-разработчик».
     *
     * Роль намеренно НЕ входит в матрицу прав: там не настроенный пункт считается
     * разрешённым (см. allows()), поэтому новая роль получила бы полный доступ
     * ко всей админке. Здесь — явный белый список, всё остальное запрещено.
     *
     * Строки внутри этих разделов дополнительно ограничиваются собственными
     * регатами пользователя.
     *
     * @see ScopesToOwnedRegattas
     *
     * @return list<class-string>
     */
    public static function developerAdminAllowed(): array
    {
        return [
            RegattaResource::class,
            RegattaEntryResource::class,
            PendingRegattaEntryResource::class,
            ArchivedRegattaEntryResource::class,
            RegattaResultResource::class,
            RaceResultResource::class,
            RegattaEntryDocumentTypeResource::class,
        ];
    }

    /**
     * Ссылки на разделы админки для выпадающего меню в шапке сайта,
     * отфильтрованные по правам текущего пользователя.
     *
     * Единый источник для всех шапок: раньше список был захардкожен в четырёх
     * местах, расходился между ними и игнорировал матрицу прав — судья видел
     * ссылки на закрытые для него разделы.
     *
     * Панель указывается явно: шапка рендерится в том числе внутри панели
     * `user` (renderHook), а без явного панели getUrl() берёт текущую и
     * собирает несуществующий маршрут filament.user.resources.*.
     *
     * @return list<array{url: string, label: string}>
     */
    public static function adminMenuLinks(): array
    {
        $sections = [
            RegattaResource::class => 'Регаты',
            RegattaResultResource::class => 'Результаты',
            RegattaEntryResource::class => 'Заявки на регаты',
            TeamResource::class => 'Команды',
            YachtResource::class => 'Яхты',
            UserResource::class => 'Пользователи',
            TeamRatingResource::class => 'Рейтинги',
            NewsResource::class => 'Новости',
            GalleryResource::class => 'Галерея',
        ];

        $links = [];

        foreach ($sections as $class => $label) {
            if (self::allows($class)) {
                $links[] = ['url' => $class::getUrl('index', panel: 'admin'), 'label' => $label];
            }
        }

        return $links;
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
            AccessControlSettings::class,
            EditProfile::class,
            Dashboard::class,
            // Содержит конфигурацию AI-провайдера и имеет собственное
            // ограничение «только администратор».
            AiNewsSettings::class,
            // Имеет собственное ограничение «только администратор».
            YachtDocumentSettings::class,
            // Финансовый контур: собственное ограничение «администратор + бухгалтер».
            // @see \App\Filament\Concerns\RestrictsToPaymentRoles
            PaymentRegistryResource::class,
            PaymentRegistryLogResource::class,
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

        // Админ-разработчик — только явно разрешённые разделы (deny by default).
        if ($role === SystemRole::DeveloperAdmin) {
            return in_array($class, self::developerAdminAllowed(), true);
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
                'key' => self::keyFor($class),
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
