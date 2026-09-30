<?php

namespace App\Filament\Plattform\Pages;

use App\Support\Papierkorb\PapierkorbSeite;

/** Papierkorb ueber alle Mandanten, mit endgueltigem Loeschen. */
class Papierkorb extends PapierkorbSeite
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_platform_admin;
    }

    protected function plattform(): bool
    {
        return true;
    }
}
