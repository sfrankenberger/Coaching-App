<?php

namespace App\Filament\Middleware;

use App\Tenancy\Branding;
use Closure;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hauptfarbe des Coach-Bereichs aus dem Branding des Mandanten.
 * Panel-Farben werden beim Booten festgelegt, der Mandant ist erst im Request bekannt,
 * darum hier als Middleware.
 */
class ApplyTenantBranding
{
    public function __construct(protected Branding $branding) {}

    public function handle(Request $request, Closure $next): Response
    {
        $primary = (string) $this->branding->get('primary');

        if (preg_match('/^#[0-9a-f]{6}$/i', $primary)) {
            FilamentColor::register(['primary' => Color::hex($primary)]);
        }

        return $next($request);
    }
}
