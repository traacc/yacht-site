<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

use App\Http\Middleware\FilamentAuthenticate;

use Filament\View\PanelsRenderHook;

use App\Filament\Widgets\UpcomingBirthdaysWidget;

use Filament\Navigation\NavigationItem;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->profile(\App\Filament\Pages\EditProfile::class, isSimple: false) 
            ->login()
            ->darkMode(false)
            ->favicon(asset('favicon.jpg?v=4'))
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->navigationItems([
                NavigationItem::make('Профиль')
                    ->url(fn (): string => \App\Filament\Pages\EditProfile::getUrl(), shouldOpenInNewTab: false)
                    ->icon('profile')
                    ->isActiveWhen(fn () => request()->routeIs('filament.admin.auth.profile'))
                    
            ])
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
                //AccountWidget::class,
                //FilamentInfoWidget::class,
                UpcomingBirthdaysWidget::class,
                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\UpcomingRegattas::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
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
