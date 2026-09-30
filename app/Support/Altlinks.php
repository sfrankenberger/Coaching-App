<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Post;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\Unit;

/**
 * Alte Adressen aus dem WordPress-Mitgliederbereich (/mitgliederbereich/...) auf die neue App abbilden.
 * Beim Umschalten leitet leawernli.ch alles unter /mitgliederbereich/ hierher (docs/07, 4.3); tiefe Links
 * aus alten Mails, Kalendern und Push-Nachrichten landen so auf der richtigen Seite statt nur auf der Startseite.
 *
 * Reihenfolge: erst die Zuordnung des Mandanten (settings.altlinks, alt => neu, laengster Treffer zuerst),
 * dann die eingebaute Tabelle nach Bereichsnamen, Kennungen ueber legacy_id (WordPress-ID) oder Slug.
 */
class Altlinks
{
    /** Bereichsnamen des alten Mitgliederbereichs => Routenname in der App. */
    public const BEREICHE = [
        'kurse' => 'kurse.index', 'kurs' => 'kurse.index', 'kursraum' => 'kurse.index', 'programm' => 'kurse.index', 'programme' => 'kurse.index', 'arbeitsbuch' => 'kurse.index', 'arbeitsbuecher' => 'kurse.index',
        'termine' => 'termine.index', 'termin' => 'termine.index', 'kalender' => 'termine.index', 'calls' => 'termine.index', 'aufzeichnungen' => 'termine.index',
        'gespraech' => 'gespraech.index', 'gespraeche' => 'gespraech.index', 'chat' => 'gespraech.index', 'nachrichten' => 'gespraech.index', 'nachricht' => 'gespraech.index',
        'aufgaben' => 'aufgaben.index', 'aufgabe' => 'aufgaben.index', 'todo' => 'aufgaben.index',
        'notizen' => 'notizen.index', 'notiz' => 'notizen.index', 'journal' => 'journal.index', 'tagebuch' => 'journal.index',
        'reflexion' => 'reflexion.index', 'reflexionen' => 'reflexion.index', 'wochencheck' => 'reflexion.index', 'woche' => 'reflexion.index',
        'material' => 'material.index', 'materialien' => 'material.index', 'ressourcen' => 'material.index', 'downloads' => 'material.index', 'dateien' => 'material.index',
        'impulse' => 'impulse.index', 'impuls' => 'impulse.index', 'neuigkeiten' => 'impulse.index', 'news' => 'impulse.index', 'beitraege' => 'impulse.index', 'podcast' => 'impulse.index',
        'profil' => 'profil', 'konto' => 'profil', 'einstellungen' => 'profil', 'mitgliedschaft' => 'profil', 'buchungen' => 'profil', 'benachrichtigungen' => 'profil',
        'community' => 'community', 'fragen' => 'community', 'frage' => 'community', 'leute' => 'community',
        'buchen' => 'buchen.index', 'klarheitsgespraech' => 'buchen.index',
        'hilfe' => 'hilfe', 'technik' => 'hilfe', 'nachschlagen' => 'nachschlagen.index', 'suche' => 'nachschlagen.index', 'merkliste' => 'merkliste', 'projekte' => 'projekte.index', 'themen' => 'themen.index',
        'login' => 'anmelden', 'anmelden' => 'anmelden', 'logout' => 'anmelden',
    ];

    public function __construct(protected ?Tenant $tenant) {}

    /** Neue URL (relativ) fuer einen alten Pfad und seine Query-Parameter. */
    public function ziel(string $pfad, array $query = []): string
    {
        $pfad = trim(strtolower($pfad), '/');
        $pfad = preg_replace('~^mitgliederbereich/?~', '', $pfad) ?? '';

        if ($eigen = $this->eigene($pfad)) {
            return $eigen;
        }

        $teile = array_values(array_filter(explode('/', $pfad), fn ($t) => $t !== ''));
        $bereich = $teile[0] ?? '';
        $kennung = $teile[1] ?? null;
        $unter = $teile[2] ?? null;
        $unterKennung = $teile[3] ?? null;

        // Kennungen auch aus der Query: ?kurs=slug&lektion=123, ?termin=5, ?p=77
        $kurs = $query['kurs'] ?? $query['programm'] ?? $query['course'] ?? null;
        $lektion = $query['lektion'] ?? $query['einheit'] ?? $query['unit'] ?? $query['lesson'] ?? null;
        $termin = $query['termin'] ?? $query['event'] ?? null;
        $beitrag = $query['p'] ?? $query['beitrag'] ?? $query['post'] ?? null;

        if (in_array($bereich, ['lektion', 'einheit', 'lesson', 'unit'], true)) {
            $lektion = $kennung ?? $lektion;
        }
        if (in_array($unter, ['lektion', 'einheit', 'lesson', 'unit', 'schritt', 'woche', 'modul'], true) && $unterKennung) {
            $lektion = in_array($unter, ['schritt', 'woche', 'modul'], true) ? $lektion : $unterKennung;
            $schritt = in_array($unter, ['schritt', 'woche', 'modul'], true) ? $unterKennung : null;
        }
        if (in_array($bereich, ['kurs', 'kurse', 'kursraum', 'programm', 'programme', 'arbeitsbuch', 'arbeitsbuecher'], true) && $kennung) {
            $kurs = $kennung;
        }

        if ($lektion && ($unit = $this->unit($lektion, $kurs))) {
            return route('kurse.einheit', [$unit->program, $unit], false);
        }
        if ($kurs && ($program = $this->program($kurs))) {
            if (! empty($schritt) && ctype_digit((string) $schritt)) {
                return route('kurse.schritt', [$program, (int) $schritt], false);
            }

            return route(match ($unter) {
                'austausch', 'gruppe', 'chat' => 'kurse.austausch', 'fragen' => 'kurse.fragen', default => 'kurse.show'
            }, $program, false);
        }
        if (in_array($bereich, ['termin', 'termine', 'kalender', 'calls', 'aufzeichnungen', 'aufzeichnung'], true) && ($kennung || $termin)) {
            if ($event = $this->event($kennung ?? $termin)) {
                return route('termine.show', $event, false);
            }
        }
        if (in_array($bereich, ['impulse', 'impuls', 'neuigkeiten', 'news', 'beitraege', 'beitrag'], true) && ($kennung || $beitrag)) {
            if ($post = $this->post($kennung ?? $beitrag)) {
                return route('impulse.show', $post, false);
            }
        }
        if ($bereich === '' && $beitrag && ($post = $this->post($beitrag))) {
            return route('impulse.show', $post, false);
        }
        if (in_array($bereich, ['gespraech', 'gespraeche', 'chat', 'nachrichten', 'nachricht'], true) && $kennung && ctype_digit((string) $kennung)) {
            return route('gespraech.index', absolute: false);   // alte Gespraechs-IDs sind nicht uebertragbar, die Liste zeigt das richtige
        }

        $route = self::BEREICHE[$bereich] ?? 'home';

        return route($route, absolute: false);
    }

    /** Zuordnung des Mandanten aus settings.altlinks: ['alte/adresse' => '/neue/adresse', ...], laengster Treffer gewinnt. */
    protected function eigene(string $pfad): ?string
    {
        $map = (array) ($this->tenant?->setting('altlinks') ?? []);
        $beste = null;
        $laenge = -1;
        foreach ($map as $alt => $neu) {
            $alt = trim(strtolower((string) $alt), '/');
            $alt = preg_replace('~^mitgliederbereich/?~', '', $alt) ?? '';
            if (! is_string($neu) || $neu === '') {
                continue;
            }
            if ($pfad === $alt || ($alt !== '' && str_starts_with($pfad, $alt.'/')) || ($alt === '' && $pfad === '')) {
                if (strlen($alt) > $laenge) {
                    $laenge = strlen($alt);
                    $beste = $neu;
                }
            }
        }

        return $beste;
    }

    protected function program(string|int $kennung): ?Program
    {
        return Program::where(fn ($w) => $w->where('slug', $kennung)->orWhere('legacy_id', (string) $kennung))->first();
    }

    protected function unit(string|int $kennung, string|int|null $kurs): ?Unit
    {
        $q = Unit::query()->with('program')->where('legacy_id', (string) $kennung);
        if ($kurs && ($program = $this->program($kurs))) {
            $q->where('program_id', $program->id);
        }
        $unit = $q->first();

        return $unit?->program ? $unit : null;
    }

    protected function event(string|int $kennung): ?Event
    {
        return Event::where(fn ($w) => $w->where('legacy_id', (string) $kennung)->orWhere('id', ctype_digit((string) $kennung) ? (int) $kennung : 0))->first();
    }

    protected function post(string|int $kennung): ?Post
    {
        return Post::where(fn ($w) => $w->where('legacy_id', (string) $kennung)->orWhere('slug', (string) $kennung))->first();
    }
}
