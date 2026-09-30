<?php

namespace App\Filament\Coach\Pages;

use App\Support\Papierkorb\PapierkorbSeite;

/** Papierkorb des Mandanten: zurueckholen ja, endgueltig loeschen nur in der Plattform. */
class Papierkorb extends PapierkorbSeite
{
    protected function plattform(): bool
    {
        return false;
    }
}
