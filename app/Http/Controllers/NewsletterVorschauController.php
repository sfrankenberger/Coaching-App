<?php

namespace App\Http\Controllers;

use App\Models\Kontakt;
use App\Models\Newsletter;
use App\Models\Serie;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Vorschau fuer das Team: ein Newsletter, ein Serienschritt oder das Grundlayout mit Musterinhalt, wie bei einer Empfaengerin. */
class NewsletterVorschauController extends Controller
{
    public function newsletter(Request $request, Newsletter $newsletter): View
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);

        return view('newsletter.vorschau', ['n' => $newsletter, 'k' => $this->muster($request)]);
    }

    public function serie(Request $request, Serie $serie, int $schritt = 0): View
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $s = $serie->schritt($schritt);
        abort_unless($s, 404);

        return view('newsletter.vorschau', ['n' => new Newsletter(['betreff' => $s['betreff'] ?? $serie->titel, 'titel' => $s['titel'] ?? null, 'text' => $s['text'] ?? '', 'bloecke' => $s['bloecke'] ?? null, 'bild_url' => $s['bild_url'] ?? null, 'knopf_text' => $s['knopf_text'] ?? null, 'knopf_url' => $s['knopf_url'] ?? null]), 'k' => $this->muster($request)]);
    }

    /** Das Grundlayout: Logo, Farben, Schrift, Fusszeile, Social-Links, mit Musterbausteinen. */
    public function layout(Request $request): View
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $n = new Newsletter(['betreff' => 'So sieht dein Grundlayout aus', 'bloecke' => [
            ['type' => 'ueberschrift', 'data' => ['text' => 'Hallo {vorname}, so sehen deine Mails aus', 'groesse' => 'gross']],
            ['type' => 'text', 'data' => ['html' => '<p>Das ist dein Grundlayout: oben dein Logo, dann der Inhalt in der Karte, unten die Fusszeile mit Abmeldelink. Farben und Schriften kommen aus den Einstellungen unter "Aussehen". <strong>Fett</strong>, <em>kursiv</em> und <a href="https://example.com">Links</a> gehen im Text.</p><ul><li>Bausteine: Überschrift, Text, Bild, Knopf</li><li>Trenner, Zitat, Kasten, Angebot</li></ul>']],
            ['type' => 'knopf', 'data' => ['text' => 'So sieht ein Knopf aus', 'url' => 'https://example.com', 'stil' => 'voll', 'ausrichtung' => 'mitte']],
            ['type' => 'trenner', 'data' => ['art' => 'linie']],
            ['type' => 'zitat', 'data' => ['text' => 'Ein Zitat steht so da, mit einem feinen Strich in deiner Hauptfarbe.', 'von' => 'Du']],
            ['type' => 'kasten', 'data' => ['titel' => 'Ein Kasten', 'html' => '<p>Für Hinweise, Termine oder eine kurze Zusammenfassung.</p>']],
        ]]);

        return view('newsletter.vorschau', ['n' => $n, 'k' => $this->muster($request)]);
    }

    protected function muster(Request $request): Kontakt
    {
        return new Kontakt(['email' => $request->user()->email, 'name' => $request->user()->name, 'token' => str_repeat('x', 40)]);
    }
}
