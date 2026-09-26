<?php

namespace App\Providers\Filament;

use App\Tenancy\Middleware\IdentifyTenant;
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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Plattform-Verwaltung unter /plattform: Mandanten, Domains. Nur fuer Plattform-Admins.
 */
class PlattformPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('plattform')
            ->path('plattform')
            ->login(fn () => redirect()->route('anmelden', ['weiter' => '/plattform']))
            ->brandName('Plattform')
            ->colors(['primary' => Color::Slate])
            ->darkMode(false)
            ->discoverResources(in: app_path('Filament/Plattform/Resources'), for: 'App\Filament\Plattform\Resources')
            ->discoverPages(in: app_path('Filament/Plattform/Pages'), for: 'App\Filament\Plattform\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
