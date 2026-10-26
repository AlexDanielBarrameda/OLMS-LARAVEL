<?php

namespace App\Providers\Filament;

use App\Filament\User\Pages\UserDashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

class UserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('user')
            ->path('user')
            ->brandName('Book Garden Library')
            ->topNavigation()
            // ->login() // Disable default Filament login
            // ->loginUrl('/login') // Redirect to your custom unified login page
            ->registration()
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.theme')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.user.hooks.styles')
            )
            // No dropdown: the avatar links straight to the profile (which holds Sign out)
            ->userMenu(false)
            // In-app due-date reminders (sent daily by app:send-due-reminders); loaded with the page, no polling
            ->databaseNotifications(isLazy: false)
            ->databaseNotificationsPolling(null)
            // Top bar: theme toggle, notifications bell, then the avatar
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): View => view('filament.user.hooks.topbar-theme')
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): View => view('filament.user.hooks.topbar-end')
            )
            ->discoverResources(in: app_path('Filament/User/Resources'), for: 'App\Filament\User\Resources')
            ->discoverPages(in: app_path('Filament/User/Pages'), for: 'App\Filament\User\Pages')
            ->pages([
                UserDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/User/Widgets'), for: 'App\Filament\User\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}