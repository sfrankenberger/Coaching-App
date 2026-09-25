<?php

namespace App\Http\Controllers;

use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Einfuehrung in acht Schritten beim ersten Besuch, jederzeit wieder aufrufbar.
 * Texte kommen aus tenants.settings.onboarding.steps, sonst die Vorgabe hier
 * (mit dem Namen der Coachin aus dem Branding).
 */
class WillkommenController extends Controller
{
    public function __construct(protected CurrentTenant $current, protected Branding $branding) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $membership = $user->membershipIn();

        return view('willkommen', [
            'schritte' => $this->steps($request),
            'telefon' => $user->phone,
            'gesehen' => (bool) $membership?->setting('onboarding_seen_at'),
            'pushMoeglich' => filled($this->current->get()?->setting('push.vapid.public')),
        ]);
    }

    public function fertig(Request $request): RedirectResponse
    {
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:40']]);
        $user = $request->user();
        if (array_key_exists('phone', $data) && filled($data['phone'])) {
            $user->forceFill(['phone' => trim($data['phone'])])->save();
        }
        if ($m = $user->membershipIn()) {
            $settings = $m->settings ?? [];
            $settings['onboarding_seen_at'] = now()->toIso8601String();
            $m->forceFill(['settings' => $settings])->save();
        }

        return redirect()->route('home')->with('meldung', 'Schön, dass du da bist. Los geht es.');
    }

    protected function steps(Request $request): array
    {
        $tenant = $this->current->get();
        $custom = $tenant?->setting('onboarding.steps');
        if (is_array($custom) && $custom !== []) {
            return array_values($custom);
        }
        $coach = $tenant?->setting('coach_name') ?: ($tenant?->owners()->first()?->vorname() ?? $this->branding->appName());
        $app = $this->branding->appName();
        $einzel = $request->user()->roleIn()?->value === 'client';

        return [
            ['icon' => 'hand-holding-heart', 'titel' => 'Hier bist du richtig', 'text' => '<p>Alles, was zu unserer Zusammenarbeit gehört, liegt ab jetzt an einem Ort.</p><p>'.($einzel ? "Deine Termine mit {$coach}, deine Unterlagen, dein Platz zum Schreiben und der direkte Draht." : "Deine Kurswochen, die Termine, die Aufzeichnungen, dein Platz zum Schreiben und der direkte Draht zu {$coach}.").'</p><p>Zwei Minuten einrichten, dann einfach ausprobieren. Kaputtmachen kannst du nichts.</p>'],
            ['icon' => 'mobile-screen-button', 'titel' => 'Aufs Handy legen', 'text' => "<p>Damit du nicht jedes Mal die Adresse eintippen musst, legst du dir {$app} wie eine App auf den Startbildschirm.</p><p><strong>iPhone:</strong> in Safari unten auf Teilen tippen, dann <em>Zum Home-Bildschirm</em>.</p><p><strong>Android:</strong> in Chrome oben rechts auf die drei Punkte, dann <em>App installieren</em>.</p><p>Ab dann reicht ein Tipp auf das Symbol.</p>"],
            ['icon' => 'bell', 'titel' => 'Damit du nichts verpasst', 'text' => "<p>Öffne die App über das neue Symbol und schalte Push ein. Auf dem iPhone geht es nur so.</p><p>Du bekommst dann eine kurze Nachricht, wenn {$coach} dir schreibt, ein Termin ansteht oder eine Aufzeichnung da ist. Ein paar pro Woche, nicht mehr.</p><p>Magst du das nicht? Dann kommt abends eine Sammelmail, wenn etwas Neues da ist.</p>", 'push' => true],
            ['icon' => 'compass', 'titel' => 'Wo ist was', 'text' => '<p><strong>Kurse</strong> führt dich Woche für Woche durch das Programm. Dort steht, was diese Woche dran ist.</p><p><strong>Termine</strong> zeigt die nächsten Calls, mit Knopf direkt in den Zoom-Raum.</p><p><strong>Journal</strong> sammelt deine Aufgaben, Notizen und Reflexionen. <strong>Material</strong> enthält alle Unterlagen und Aufzeichnungen, <strong>Impulse</strong> Beiträge und Podcast.</p>'."<p>Und unten rechts ist immer ein runder Knopf. Damit schreibst du {$coach} von jeder Seite aus, ohne zu suchen.</p>"],
            ['icon' => 'feather-pointed', 'titel' => 'Was von dir kommt', 'text' => "<p>Jede Woche gibt es ein paar kleine Sachen: eine Frage für den Fragentag, eine kurze Reflexion am Ende der Woche, manchmal eine Aufgabe von {$coach}.</p><p>Du findest alles unter <strong>Journal</strong>. Nichts davon musst du, alles darfst du.</p><p>Im Gespräch kannst du auch eine Sprachnachricht schicken. Tippen, reden, fertig.</p>"],
            ['icon' => 'lock', 'titel' => 'Du entscheidest, wer es sieht', 'text' => "<p>Alles, was du schreibst, ist zuerst <strong>nur für dich</strong>. Auch deine Reflexionen und die Antworten im Kurs.</p><p>Bei jedem Eintrag entscheidest du: nur ich, nur {$coach}, oder die Gruppe.</p><p>Was du mit {$coach} teilst, liest sie vor eurem nächsten Gespräch. Sie fragt nie nach dem, was du für dich behältst.</p>"],
            ['icon' => 'phone', 'titel' => "Wie erreicht dich {$coach}?", 'text' => "<p>Wenn einmal etwas kurzfristig ist, ein Termin verschoben wird oder die Technik streikt, meldet sich {$coach} gern direkt bei dir.</p><p>Deine Nummer sieht nur {$coach}. Du kannst sie jederzeit im Profil wieder löschen.</p>", 'telefon' => true],
            ['icon' => 'life-ring', 'titel' => 'Wenn es mal hakt', 'text' => "<p>Dann melde dich, ganz ohne schlechtes Gewissen. Du musst hier nichts können.</p><p>Bei allem Inhaltlichen: schreib {$coach} im Gespräch.</p><p>Bei Technik: im Profil unter <strong>Hilfe</strong> gibt es ein kurzes Formular. Ein Satz genügt.</p><p>Diese Einführung kannst du jederzeit wieder öffnen, im Profil ganz unten.</p>"],
        ];
    }
}
