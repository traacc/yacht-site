<?php

namespace App\Providers\Filament;

use App\Filament\User\Pages\EditProfile;
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

use Filament\FontProviders\LocalFontProvider;

use Filament\View\PanelsRenderHook;

use Filament\Navigation\NavigationItem;


class UserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('user')
            ->path('user')
            ->login()
            ->profile(\App\Filament\User\Pages\EditProfile::class, isSimple: false) 
            ->darkMode(false)
            ->favicon(asset('favicon.jpg?v=4'))
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/User/Resources'), for: 'App\Filament\User\Resources')
            ->discoverPages(in: app_path('Filament/User/Pages'), for: 'App\Filament\User\Pages')
            ->navigationItems([
                NavigationItem::make('Профиль')
                    ->url(fn (): string => \App\Filament\User\Pages\EditProfile::getUrl(), shouldOpenInNewTab: false)
                    ->icon('profile')
                    ->isActiveWhen(fn () => request()->routeIs('filament.user.auth.profile'))
                    
            ])
            ->renderHook(
                name: PanelsRenderHook::TOPBAR_BEFORE,
                hook: fn () => view('filament.user-header')
            )
            ->renderHook(
                name: PanelsRenderHook::BODY_END,
                hook: fn () => view('filament.user-footer')
            )
            ->viteTheme('resources/css/filament/user/theme.css')
            ->discoverWidgets(in: app_path('Filament/User/Widgets'), for: 'App\Filament\User\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            /*
            ->font(
                'TTLakesCondensed-DemiBold',
                url: asset('css/app.css'), 
                provider: LocalFontProvider::class
            )
            ->font(
                'Montserrat',
                url: asset('css/app.css'), 
                provider: LocalFontProvider::class
            )
            */
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                //AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ]);
    }
}
