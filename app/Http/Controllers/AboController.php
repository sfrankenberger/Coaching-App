<?php

namespace App\Http\Controllers;

use App\Shop\Abo;
use App\Shop\Stripe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Abo aus dem Profil: Kundenportal von Stripe (Zahlungsmittel, Rechnungen, kuendigen). */
class AboController extends Controller
{
    public function portal(Request $request, Stripe $stripe, Abo $abo): RedirectResponse
    {
        $kunde = Stripe::kundeVon($request->user());
        if (! $kunde || ! $stripe->konfiguriert()) {
            return redirect()->to(route('profil').'#buchungen')->with('fehler', 'Für dein Abo gibt es hier noch kein Kundenportal. Schreib uns kurz, dann kümmern wir uns darum.');
        }
        try {
            return redirect()->away($stripe->portal($kunde, route('profil').'#buchungen'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->to(route('profil').'#buchungen')->with('fehler', 'Das Kundenportal ist gerade nicht erreichbar. Versuch es gleich nochmal.');
        }
    }
}
