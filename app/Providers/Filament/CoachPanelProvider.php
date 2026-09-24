<?php

namespace App\Providers\Filament;

use App\Filament\Middleware\ApplyTenantBranding;
use App\Tenancy\Branding;
use App\Tenancy\Middleware\IdentifyTenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Coach-Bereich unter /coach: fuer owner und team des Mandanten dieser Domain.
 * Ersetzt die Werkstatt aus WordPress. Anmeldung laeuft ueber die App (Magic Link),
 * nicht ueber ein eigenes Filament-Login.
 */
class CoachPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('coach')
            ->path('coach')
            ->login(fn () => redirect()->route('anmelden', ['weiter' => '/coach']))
            ->brandName(fn () => app(Branding::class)->appName())
            ->brandLogo(fn () => app(Branding::class)->get('logo_url'))
            ->favicon(fn () => app(Branding::class)->get('icon_url'))
            ->colors(['primary' => Color::Stone])
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Coach/Resources'), for: 'App\Filament\Coach\Resources')
            ->discoverPages(in: app_path('Filament/Coach/Pages'), for: 'App\Filament\Coach\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Coach/Widgets'), for: 'App\Filament\Coach\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->userMenuItems([
                MenuItem::make()->label('Zur App')->url('/')->icon('heroicon-o-device-phone-mobile'),
            ])
            ->middleware([
                IdentifyTenant::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                ApplyTenantBranding::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
