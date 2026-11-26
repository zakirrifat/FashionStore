<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()            // Filament limits sign-in to 5 attempts per minute
            ->passwordReset()
            ->profile(isSimple: false) // "Profile": change name, email, password and set up two-step sign-in
            // Two-step sign-in with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…),
            // with recovery codes. Required for every admin/staff user unless ADMIN_REQUIRE_2FA=false (local only).
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: (bool) config('app.admin_require_2fa'),
            )
            ->brandName(config('app.name').' Admin')
            // Uploaded logo (Store settings › Brand); falls back to the name above.
            ->brandLogo(fn () => \App\Support\Brand::url('logo_dark'))
            ->darkModeBrandLogo(fn () => \App\Support\Brand::url('logo_light'))
            ->brandLogoHeight('2.5rem')
            ->favicon(fn () => \App\Support\Brand::url('icon_32') ?? '/favicon.svg')
            ->colors([
                'primary' => Color::Zinc,
            ])
            // Bell in the top bar (see App\Services\AdminNotifier); red while there are unread alerts.
            ->databaseNotifications(livewireComponent: \App\Livewire\AdminNotificationBell::class)
            ->databaseNotificationsPolling('30s')
            ->authenticatedRoutes(fn () => \Illuminate\Support\Facades\Route::post('alerts/open', \App\Http\Controllers\AdminAlertController::class)
                ->name('alerts.open'))
            ->navigationGroups(['Catalogue', 'Sales', 'Content', 'People', 'Settings'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
                \App\Http\Middleware\SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
