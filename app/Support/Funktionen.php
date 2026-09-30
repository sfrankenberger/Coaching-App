<?php

namespace App\Support;

use App\Tenancy\CurrentTenant;
use Laravel\Pennant\Feature;

/** Kurzer Zugriff auf die Funktionsschalter je Mandant (Pennant, tenants.settings.features). */
class Funktionen
{
    /** Schaltbare Bereiche mit Vorgabe (aus, bis sie wieder eingebaut werden). */
    public const SCHALTER = [
        'zeitleiste' => ['Meine Zeitleiste (Journal)', false],
        'projekte' => ['Meine Projekte', false],
    ];

    /** Direkt aus den Einstellungen (nicht ueber den Pennant-Speicher, damit ein Umschalten sofort gilt). */
    public static function an(string $name): bool
    {
        $tenant = app(CurrentTenant::class)->get();
        if (! $tenant) {
            return false;
        }
        if (array_key_exists($name, self::SCHALTER)) {
            return (bool) $tenant->setting("features.$name", self::SCHALTER[$name][1]);
        }

        return (bool) Feature::for($tenant)->active($name);
    }
}
