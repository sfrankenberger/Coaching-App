<?php

namespace App\Http\Controllers;

use App\Support\Altlinks;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Weiche fuer alte Adressen: leawernli.ch/mitgliederbereich/... leitet beim Umschalten hierher.
 * Nicht angemeldete Personen gehen ueber /anmelden und landen danach am Ziel (weiter=...).
 */
class AltlinkController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $current, ?string $pfad = null): RedirectResponse
    {
        $ziel = (new Altlinks($current->get()))->ziel((string) $pfad, $request->query());

        if ($request->user() || in_array($ziel, ['/anmelden', route('anmelden', absolute: false)], true)) {
            return redirect()->to($ziel, 301);
        }

        return redirect()->route('anmelden', $ziel === '/' ? [] : ['weiter' => $ziel]);
    }
}
