<?php

namespace App\Providers\Filament;

use App\Filament\Pages\EditProfile;
use App\Filament\Widgets\RequestsOverview;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UpcomingBirthdaysWidget;
use App\Filament\Widgets\UpcomingRegattas;
use App\Http\Middleware\FilamentAuthenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->profile(EditProfile::class, isSimple: false)
            ->login()
            // Предупреждение при уходе со страницы с несохранёнными изменениями:
            // страницы создания/редактирования ресурсов, открытые модалки действий,
            // а также кастомные Page с трейтом HasUnsavedDataChangesAlert.
            ->unsavedChangesAlerts()
            // Тот же колокольчик, что и в ЛК: уведомления администраторам.
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->darkMode(false)
            ->favicon(asset('favicon.svg?v=4'))
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('2rem')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->navigationGroups([
                NavigationGroup::make('Регаты'),
                NavigationGroup::make('Рейтинги'),
                NavigationGroup::make('Команды и участники'),
                NavigationGroup::make('Яхты'),
                NavigationGroup::make('Услуги'),
                NavigationGroup::make('Финансы'),
                NavigationGroup::make('Объявления'),
                NavigationGroup::make('Обращения'),
                NavigationGroup::make('Сайт'),
                NavigationGroup::make('Администрирование'),
            ])
            /*->navigationItems([
                NavigationItem::make('Профиль')
                    ->url(fn (): string => \App\Filament\Pages\EditProfile::getUrl(), shouldOpenInNewTab: false)
                    ->icon('profile')
                    ->isActiveWhen(fn () => request()->routeIs('filament.admin.auth.profile'))

            ])*/
            /*
            ->renderHook(
                name: PanelsRenderHook::TOPBAR_BEFORE,
                hook: fn () => view('filament.user-header')
            )
            ->renderHook(
                name: PanelsRenderHook::BODY_END,
                hook: fn () => view('filament.user-footer')
            )
            */
            ->viteTheme('resources/css/filament/user/theme.css')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
                UpcomingBirthdaysWidget::class,
                StatsOverview::class,
                RequestsOverview::class,
                UpcomingRegattas::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ])->topNavigation(false)->renderHook(
                name: PanelsRenderHook::BODY_START,
                hook: fn () => view('components.nav'));
    }
}
