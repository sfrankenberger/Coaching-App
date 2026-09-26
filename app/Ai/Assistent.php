<?php

namespace App\Ai;

use App\Chat\Chat;
use App\Coach\Lage;
use App\Filament\Coach\Resources\BookingTypes\BookingTypeResource;
use App\Filament\Coach\Resources\Events\EventResource;
use App\Filament\Coach\Resources\Materials\MaterialResource;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Filament\Coach\Resources\Offers\OfferResource;
use App\Filament\Coach\Resources\Podcast\PodcastEpisodeResource;
use App\Filament\Coach\Resources\Posts\PostResource;
use App\Filament\Coach\Resources\Programs\ProgramResource;
use App\Filament\Coach\Resources\Questions\QuestionResource;
use App\Filament\Coach\Resources\Tasks\TaskResource;
use App\Filament\Coach\Resources\Tools\ToolResource;
use App\Filament\Coach\Resources\Topics\TopicResource;
use App\Models\Booking;
use App\Models\Entitlement;
use App\Models\Event;
use App\Models\Membership;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\ProgramMember;
use App\Models\Resource;
use App\Models\Tool;
use App\Models\Unit;
use App\Models\User;
use App\Support\Zeit;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Str;
use Throwable;

/**
 * Der digitale Assistent der Coachin (wie lea-assistent und lea-steuerpult): beantwortet
 * "wo finde ich was" und "wie mache ich was" aus dem Wissen ueber die App, und Betriebsfragen
 * ("was hat Nicole gebucht?") aus den Fakten der App. Die KI macht daraus einen Satz, erfindet nichts.
 */
class Assistent
{
    public const VORSCHLAEGE = [
        'Wo sehe ich, wer auf meine Antwort wartet?',
        'Wie schicke ich allen im Kurs etwas?',
        'Wie gebe ich eine Aufzeichnung frei?',
        'Wie schlage ich jemandem Termine vor?',
        'Wo finde ich, wer beim Call dabei war?',
    ];

    protected const STOP = ['ist', 'die', 'der', 'das', 'und', 'was', 'hat', 'wer', 'wie', 'wann', 'rechnung', 'rechnungen', 'termin', 'termine', 'gebucht', 'verschickt', 'bezahlt', 'offen', 'kurs', 'kurse', 'sitzung', 'sitzungen', 'braucht', 'finde', 'noch', 'schon', 'von', 'bei', 'mit', 'für', 'fuer', 'eine', 'einen', 'meine', 'ihre', 'mir', 'mich', 'sie', 'ihr', 'auch', 'denn', 'mal', 'bitte', 'habe', 'haben', 'wurde', 'worden', 'wo', 'sehe', 'ich', 'nicht', 'kann', 'wieder', 'gerade', 'heute', 'morgen', 'letzte', 'letzten', 'nächste', 'naechste', 'call', 'zoom', 'app', 'aufgabe', 'aufgaben', 'nachricht', 'gespräch', 'gespraech'];

    public function __construct(
        protected CurrentTenant $current,
        protected Anthropic $ai,
        protected Branding $branding,
        protected Lage $lage,
        protected Chat $chat,
    ) {}

    /** Antwort auf eine Frage der Coachin: Satz der KI plus die Fakten, aus denen er stammt. */
    public function antwort(string $frage, User $coach): array
    {
        $frage = trim($frage);
        $gefunden = $this->personenAusFrage($frage);
        $fakten = [];
        if ($gefunden['unklar']) {
            $fakten['mehrdeutig'] = $gefunden['unklar'];
        }
        $menschen = [];
        foreach ($gefunden['leute'] as $m) {
            $menschen[] = $this->personFakten($m);
        }
        if ($menschen) {
            $fakten['menschen'] = array_map(fn ($p) => collect($p)->except(['dossier', 'gespraech_url'])->all(), $menschen);
        }
        $orte = $this->orteTreffer($frage);
        if ($orte) {
            $fakten['orte_in_der_app'] = array_map(fn ($o) => ['was' => $o['t'], 'wo' => $o['u']], $orte);
        }
        $inhalte = $menschen ? [] : $this->inhalte($frage);
        if ($inhalte) {
            $fakten['inhalte'] = array_map(fn ($i) => ['titel' => $i['titel'], 'art' => $i['art'], 'kurz' => $i['kurz']], $inhalte);
        }

        $text = '';
        $fehler = null;
        if (Anthropic::configured($this->current->get())) {
            try {
                $text = trim($this->ai->text(
                    'Frage: '.$frage."\n\nFakten aus der App:\n".json_encode($fakten ?: ['hinweis' => 'keine besonderen Fakten zu dieser Frage'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    $this->system(), 700,
                )['text']);
            } catch (Throwable $e) {
                report($e);
                $fehler = 'Die KI antwortet gerade nicht, hier sind die Fakten dazu.';
            }
        } else {
            $fehler = $fakten ? 'Ohne KI-Schlüssel gibt es nur die Fakten.' : 'Ohne KI-Schlüssel kann ich hier nur Menschen, Orte und Inhalte nachschlagen. Nenn mir einen Namen oder ein Stichwort.';
        }

        return ['text' => $text, 'fehler' => $fehler, 'menschen' => $menschen, 'orte' => $orte, 'inhalte' => $inhalte, 'mehrdeutig' => $gefunden['unklar']];
    }

    /* ---------- Menschen ---------- */

    /** Namen und Mailadressen in der Frage erkennen. Eindeutig oder mehrdeutig. */
    public function personenAusFrage(string $frage, int $max = 4): array
    {
        preg_match_all('~[\p{L}][\p{L}\-\.@]{2,}~u', $frage, $m);
        $leute = Membership::query()->where('status', 'active')->with('user:id,name,email')->get()->filter->user;
        $eindeutig = [];
        $unklar = [];
        foreach ($m[0] as $wort) {
            $wl = mb_strtolower(trim($wort, '.'));
            if (in_array($wl, self::STOP, true)) {
                continue;
            }
            $genau = $leute->filter(function (Membership $x) use ($wl) {
                $teile = preg_split('~[\s@\.\-]+~u', mb_strtolower($x->user->name.' '.$x->user->email));

                return in_array($wl, $teile, true) || mb_strtolower($x->user->email) === $wl;
            });
            if ($genau->isEmpty()) {
                continue;
            }
            if ($genau->count() > 1) {
                $unklar[$wort] = $genau->map(fn ($x) => $x->user->name.' ('.$x->user->email.')')->values()->all();
            } else {
                $eindeutig[$genau->first()->user_id] = $genau->first();
            }
        }
        // Grenzt ein zweites Wort die Sache ein, zaehlt nur noch der eindeutige Treffer
        if ($eindeutig) {
            $unklar = [];
        }

        return ['leute' => array_slice($eindeutig, 0, $max, true), 'unklar' => $unklar];
    }

    /** Fakten zu einem Menschen: Kurse, Kontingent, Termine, Gespraech, Zugaenge, Buchungen. */
    public function personFakten(Membership $m): array
    {
        $user = $m->user;
        $lage = $this->lage->fuer($m);
        $f = [
            'name' => $user->name,
            'mail' => $user->email,
            'telefon' => $user->phone ?: null,
            'rolle' => $m->role->label(),
            'dabei_seit' => $m->joined_at?->format('j.n.Y'),
            'zuletzt_da' => $m->last_seen_at ? Zeit::relativ($m->last_seen_at) : 'noch nie',
            'lage' => $lage['grund'] ?? null,
        ];
        $f['kurse'] = ProgramMember::where('user_id', $user->id)->with('program:id,title,type')->get()->filter->program
            ->map(fn ($pm) => $pm->program->title.' ('.$pm->program->typeLabel().')')->values()->all();
        $f['zugaenge'] = Entitlement::where('user_id', $user->id)->with('offer:id,title')->get()
            ->map(fn (Entitlement $e) => ($e->offer?->title ?? 'Zugang').($e->ends_at ? ' bis '.$e->ends_at->format('j.n.Y') : ' (ohne Ende)').($e->isCurrent() ? '' : ', abgelaufen'))->values()->all();
        if ($lage['kontingent']) {
            $k = $lage['kontingent'];
            $f['sitzungen'] = "{$k['gesamt']} gesamt, {$k['gehabt']} gehabt, {$k['geplant']} geplant, {$k['offen']} offen";
        }
        $termine = Event::where('user_id', $user->id)->where('is_published', true);
        $kommend = (clone $termine)->upcoming()->orderBy('starts_at')->first();
        $vorbei = (clone $termine)->past()->orderByDesc('starts_at')->first();
        $f['naechster_1zu1_termin'] = $kommend ? Zeit::wann($kommend->starts_at).' ('.$kommend->title.')' : 'keiner geplant';
        $f['letzter_1zu1_termin'] = $vorbei ? Zeit::wann($vorbei->starts_at).' ('.$vorbei->title.', Aufzeichnung: '.($vorbei->hasRecording() ? 'ja' : 'nein').')' : 'noch keiner gewesen';
        if ($lage['naechster'] ?? null) {
            $f['naechster_termin_ueberhaupt'] = Zeit::wann($lage['naechster']->starts_at).' ('.$lage['naechster']->title.')';
        }
        $f['aufgaben'] = ($lage['aufgaben'][0] ?? 0).' von '.($lage['aufgaben'][1] ?? 0).' erledigt'.(($lage['ueberfaellig'] ?? 0) ? ', '.$lage['ueberfaellig'].' überfällig' : '');
        $conv = $this->chat->directFor($user, false);
        if ($conv && ($letzte = $conv->messages()->latest('id')->first())) {
            $f['gespraech'] = 'letzte Nachricht '.Zeit::relativ($letzte->created_at).' von '.((int) $letzte->user_id === (int) $user->id ? 'ihr' : 'dir oder dem Team').(($lage['wartet'] ?? null) ? ', sie wartet auf Antwort' : '');
        } else {
            $f['gespraech'] = $conv ? 'Gespräch offen, noch keine Nachricht' : 'kein Gespräch';
        }
        $f['buchungen'] = Booking::where('user_id', $user->id)->with('type:id,title')->orderByDesc('starts_at')->limit(5)->get()
            ->map(fn (Booking $b) => ($b->type?->title ?? 'Buchung').' am '.Zeit::wann($b->starts_at).($b->status === 'abgesagt' ? ' (abgesagt)' : ''))->values()->all();
        $f['dossier'] = MembershipResource::getUrl('dossier', ['record' => $m]);
        $f['gespraech_url'] = $conv ? route('gespraech.show', $conv) : null;

        return $f;
    }

    /* ---------- Orte und Inhalte ---------- */

    /** Orte in der App mit Stichworten, damit "wo finde ich" auch ohne KI einen Link bekommt. */
    public function orte(): array
    {
        $o = [
            ['t' => 'Personen und Dossiers', 'u' => MembershipResource::getUrl(), 'w' => 'menschen personen person coachee coachees klientin kundin dossier notizen wartet ampel zuletzt'],
            ['t' => 'Termine und Aufzeichnungen', 'u' => EventResource::getUrl(), 'w' => 'termin termine call calls zoom aufzeichnung aufzeichnungen freigeben abschrift zusammenfassung teilnahme dabei'],
            ['t' => 'Programme und Kurse', 'u' => ProgramResource::getUrl(), 'w' => 'programm programme kurs kurse woche wochen lektion lektionen einheit workbook arbeitsbuch übung uebung'],
            ['t' => 'Material', 'u' => MaterialResource::getUrl(), 'w' => 'material ressource ressourcen pdf audio video download datei'],
            ['t' => 'Impulse und Neuigkeiten', 'u' => PostResource::getUrl(), 'w' => 'impuls impulse beitrag beiträge blog neuigkeit neuigkeiten news'],
            ['t' => 'Podcast', 'u' => PodcastEpisodeResource::getUrl(), 'w' => 'podcast folge folgen episode'],
            ['t' => 'Themen', 'u' => TopicResource::getUrl(), 'w' => 'thema themen schlagwort schlagworte fundus nachschlagen themenfinder'],
            ['t' => 'Werkzeuge für die Ausbildung', 'u' => ToolResource::getUrl(), 'w' => 'werkzeug werkzeuge methode methoden ausbildung'],
            ['t' => 'Aufgaben geben', 'u' => TaskResource::getUrl(), 'w' => 'aufgabe aufgaben wochenaufgabe todo'],
            ['t' => 'Fragen aus den Kursen', 'u' => QuestionResource::getUrl(), 'w' => 'frage fragen fragentag community'],
            ['t' => 'Angebote und Zugänge', 'u' => OfferResource::getUrl(), 'w' => 'angebot angebote zugang zugänge zugaenge kauf bestellung shop produkt abo'],
            ['t' => 'Buchungsarten', 'u' => BookingTypeResource::getUrl(), 'w' => 'buchung buchungen buchungsart klarheitsgespräch erstgespräch kalender block blöcke zeiten'],
            ['t' => 'Rundnachricht', 'u' => route('filament.coach.pages.rundnachricht'), 'w' => 'rundnachricht nachricht an alle mehrere kurs schicken senden'],
            ['t' => 'Einstellungen', 'u' => route('filament.coach.pages.einstellungen'), 'w' => 'einstellung einstellungen testbetrieb feed feeds telegram absender farben name'],
        ];

        return $o;
    }

    public function orteTreffer(string $frage, int $max = 3): array
    {
        $w = mb_strtolower($frage);
        $punkte = [];
        foreach ($this->orte() as $i => $o) {
            $p = 0;
            foreach (explode(' ', $o['w']) as $wort) {
                if ($wort !== '' && mb_strpos($w, $wort) !== false) {
                    $p++;
                }
            }
            if (mb_strpos($w, mb_strtolower($o['t'])) !== false) {
                $p += 2;
            }
            if ($p) {
                $punkte[$i] = $p;
            }
        }
        arsort($punkte);
        $orte = $this->orte();

        return array_map(fn ($i) => $orte[$i], array_slice(array_keys($punkte), 0, $max));
    }

    /** Inhalte zur Frage (Titel-Suche), mit Link in die App. */
    public function inhalte(string $frage, int $max = 6): array
    {
        $q = trim(preg_replace('~[?!.,]~u', ' ', $frage));
        if (mb_strlen($q) < 3) {
            return [];
        }
        $out = [];
        foreach ([Unit::class => 'Lektion', Resource::class => 'Material', Post::class => 'Impuls', PodcastEpisode::class => 'Podcast', Tool::class => 'Werkzeug'] as $class => $art) {
            foreach ($class::search($q)->take(3)->get() as $m) {
                if (count($out) >= $max) {
                    break 2;
                }
                $url = match (true) {
                    $m instanceof Unit => $m->program ? route('kurse.einheit', [$m->program, $m]) : null,
                    $m instanceof Resource => $m->hatSeite() ? route('material.show', $m) : route('material.index'),
                    $m instanceof Post => route('impulse.show', $m),
                    $m instanceof PodcastEpisode => route('impulse.folge', $m),
                    $m instanceof Tool => route('werkzeuge.show', $m),
                };
                $out[] = ['titel' => $m->title, 'art' => $art.($m instanceof Unit && $m->program ? ' aus '.$m->program->title : ''), 'url' => $url, 'kurz' => Str::limit((string) ($m->finder?->summary ?? ''), 160)];
            }
        }

        return $out;
    }

    /* ---------- Wissen ---------- */

    protected function system(): string
    {
        $coach = $this->branding->coachName();
        $support = (string) ($this->current->get()?->setting('support.name') ?: 'die Person, die die App betreut');

        return "Du bist der digitale Assistent von {$coach}. {$coach} ist Coachin und arbeitet mit ihrer eigenen App (Coach-Bereich und Teilnehmer-App). "
            .'Du beantwortest kurz und praktisch, wo sie etwas findet, wie sie etwas macht, und was zu einem Menschen aus den mitgelieferten Fakten bekannt ist. '
            .'Antworte in zwei bis fünf Sätzen oder kurzen Schritten, in Du-Form, ohne Floskeln. Nenne immer den Weg in der App, zum Beispiel: Coach-Bereich, Personen, Dossier, Reiter Termine. '
            .'Nur aus dem Wissen unten und den Fakten. Erfinde keine Funktionen und keine Zahlen, rechne nichts dazu. '
            .'Jede Aussage gehört zu genau einem Menschen, vermische nie die Angaben mehrerer Menschen. '
            .'Steht unter mehrdeutig ein Name, dann antworte nicht inhaltlich, sondern frag zurück, welche Person gemeint ist, und zähle die Namen mit Mailadresse auf. '
            ."Wenn du etwas nicht sicher weisst, sag das offen und schlag vor, {$support} zu fragen. "
            .Anthropic::STIL."\n\n".$this->wissen();
    }

    /** Was der Assistent ueber die App weiss: fest im Code, dazu der Text der Coachin aus den Einstellungen. */
    public function wissen(): string
    {
        $coach = $this->branding->coachName();
        $app = $this->branding->appName();
        $eigen = trim((string) $this->current->get()?->setting('ai.wissen'));

        $t = "SO IST DIE APP AUFGEBAUT ({$app})\n\n"
            ."COACH-BEREICH (/coach, nur für {$coach} und das Team):\n"
            ."Übersicht: Ampel über alle Menschen (rot wartet auf Antwort, gelb still oder Aufgaben offen, grün alles im Fluss) mit 'kurz nachfragen', dazu 'Neu für dich' (Geteiltes der letzten Tage) und der Wochencheck (was in dieser und der nächsten Kurswoche fehlt).\n"
            ."Personen: alle Mitgliedschaften mit Rolle, Status, Lage und Sitzungen. Ein Klick auf 'Dossier' zeigt zu einer Person: Lage, Sitzungen, nächster Termin, Meine Notizen (sieht nur das Team), Programme mit Stand, Termine (kommend und vergangen, live dabei, gesehen), Aufgaben, Geteilte Antworten, Reflexionen und Notizen mit Kommentar-Möglichkeit, Buchungen mit Vorabantworten, Vorbereitung (KI-Zusammenfassung vor einem Gespräch, 'Neu erstellen'). Im Kopf des Dossiers: Gespräch öffnen, Zeiten vorschlagen (Zeiten ankreuzen, Satz dazu, landet als Vorschlag im 1:1-Gespräch, antippen bucht), Mail, WhatsApp, Anrufen, Einladung schicken. Bei der Person unter 'Zugang': Kennzeichen 'Coach-Ausbildung' für die Werkzeuge.\n"
            ."Termine: Gruppencalls, 1:1-Sitzungen, Reflexions- und Fragentage anlegen (Art, Programm, Woche, Person, Zoom-Link). Abschnitt Aufzeichnung: Link, Dauer, Abschrift, Zusammenfassung (KI), Kapitel. Die Wache sucht nach jedem Termin bei Vimeo, holt Abschrift und Zusammenfassung und meldet 'Aufzeichnung bereit'. Freigeben per Knopf 'Freigeben' am Termin (Wahl Mail und Push). 'Aufgaben aus Zusammenfassung' legt Aufgaben je Person an. Reiter Teilnahme: wer live dabei war (Zoom-Abgleich stündlich, sonst von Hand), wer die Aufzeichnung gesehen hat, wer sich abgemeldet hat.\n"
            ."Programme: Kurse, Clubs, Hybrid-Coaching, 1:1-Begleitung (mit Sitzungen gesamt), Arbeitsbücher. Wochen (Schritte) mit Einleitung und Freischaltung, Einheiten mit Videos, Text, Übungsteilen; Teilnehmerinnen und deren Stand.\n"
            ."Material: PDFs, Audios, Videos, Links, an Programme, Wochen, Einheiten, Termine oder direkt an eine Person hängen. Vimeo-Videos bekommen Abschrift und Zusammenfassung von selbst.\n"
            ."Impulse und Neuigkeiten: Beiträge schreiben oder per Feed holen, Sichtbarkeit (alle, ein Programm, Team), Kanäle Push, Telegram, Mail, Termin in der Zukunft. Podcast: Folgen per Feed, Aufbereiten (KI) mit Kapiteln, Zusammenfassung, FAQ.\n"
            ."Themen: Schlagworte quer über alle Inhalte, mit Gruppe für die Auswahlliste im Nachschlagen. 'Themen prüfen' im Assistenten zeigt KI-Zuordnungen zum Bestätigen ('Passt') oder Ändern.\n"
            ."Werkzeuge: Methoden für die Coach-Ausbildung (wofür, wann, wann nicht, so geht es, Beispiel, Dauer, Material). Sehen nur Personen mit dem Kennzeichen 'Coach-Ausbildung' und das Team.\n"
            ."Aufgaben: Aufgaben an eine Person oder an alle im Programm, mit Woche und Wochentag. Fragen: Fragen aus den Kursräumen mit Status (offen, kommt in den Call, beantwortet, besprochen), Callwünsche.\n"
            ."Angebote: was man kaufen kann, schaltet Programme frei; Zugänge entstehen über den Shop (WooCommerce-Webhook) oder von Hand. Buchungsarten: Klarheitsgespräch, Erstgespräch, 1:1 mit Dauer, Puffer, Vorbereitungsfragen; freie Zeiten kommen aus Blöcken mit Stichwort im Google-Kalender.\n"
            ."Rundnachricht: an alle, ein Programm oder einzelne Personen, per Push, Telegram und Mail, auf Wunsch als persönliche Nachricht ins 1:1-Gespräch (dann kann jede direkt antworten) oder ins Gruppengespräch.\n"
            ."Einstellungen: Testbetrieb (Benachrichtigungen nur an Testadressen), Aussehen, Absender, Feeds, Telegram-Bot-Name, KI-Wissen für den Assistenten.\n"
            ."Assistent: Fragen (dieser Assistent), Themen prüfen, Werkzeuge, Geteiltes (Sammlungen aus dem Nachschlagen).\n\n"
            ."TEILNEHMER-APP (was die Menschen sehen, {$coach} sieht dasselbe plus Coach-Bereich):\n"
            ."Start: Hallo, Neu für dich, nächster Termin, Weiter im Kurs, offene Aufgaben, Kacheln. Kurse: Wochen mit Call, Aufgaben, Material, Reflexion, Einheiten mit Übungen, Freigabe 'darf {$coach} mitlesen'. Termine: Liste, Zoom, Aufzeichnungen mit Kapiteln, abmelden. Material: Regal mit Filtern, Merken. Impulse: Beiträge und Podcast, 'Frage dazu' ins Gespräch. Nachschlagen: ein Feld (Wort sucht, Satz fragt die KI), Themen, Vorschau, Merken, Teilen, Meine Suchen, Mein Archiv; {$coach} wählt dort Inhalte aus und schickt sie als Sammlung in 1:1-Gespräche oder als Link. Werkzeuge (nur Ausbildung). Mein Journal: Aufgaben, Notizen, Reflexion (Wochenreflexion mit drei Fragen, Teilen mit {$coach}). Gespräch: 1:1-Chat mit {$coach} (Text, Sprachnachricht, Datei, Terminvorschläge), Gruppengespräch je Kurs. Mitteilungen (Glocke). Profil: Daten, Was dich erreicht (Termin-Erinnerungen, Abendmail, Aufgaben), Push, Telegram, Kalender-Abo, Passkeys, Meine Buchungen, Hilfe. Buchen: Termin nach Art und Zeit, Vorbereitungsfragen, absagen bis zur Frist.\n\n"
            ."WIE MACHE ICH WAS\n"
            ."Eine Nachricht an alle schicken: Coach-Bereich, Rundnachricht, Empfänger wählen (alle, ein Programm, einzelne), Text, Kanäle, Senden. Mit 'als persönliche Nachricht' landet sie in jedem 1:1-Gespräch.\n"
            ."Sehen, wer auf Antwort wartet: Coach-Bereich, Übersicht, Ampel (rot = wartet), oder Personen (Spalte Lage). 'Kurz nachfragen' öffnet das Gespräch mit einem Entwurf.\n"
            ."Termine vorschlagen: Personen, Dossier, Knopf 'Zeiten vorschlagen', Zeiten ankreuzen, Satz dazu. Die Person tippt eine Zeit an, dann ist der Termin gebucht und beide sehen die Bestätigung.\n"
            ."Etwas mit einer Person teilen: Nachschlagen öffnen, suchen, beim Treffer das Teilen-Zeichen antippen, Person ankreuzen, Gruss dazu, 'In den Chat schicken'. Mehrere Treffer: Häkchen setzen, unten Name und Gruss, 'Schicken' oder 'Nur Link erzeugen'.\n"
            ."Eine Aufzeichnung freigeben: Termine, Termin öffnen, Abschnitt Aufzeichnung prüfen (Zusammenfassung), Knopf 'Freigeben' mit Mail und Push. Ohne Wache: Link und Abschrift eintragen, 'Zusammenfassen (KI)'.\n"
            ."Wer beim Call dabei war: Termine, Termin öffnen, Reiter Teilnahme. Der Zoom-Abgleich läuft stündlich; von Hand über 'live dabei'.\n"
            ."Eine Aufgabe an alle im Kurs geben: Aufgaben, 'Aufgabe anlegen', Programm wählen, 'an alle im Programm', Woche und Wochentag.\n"
            ."Auf Geteiltes antworten: Personen, Dossier, bei der Reflexion, Notiz, Aufgabe oder Antwort 'Antworten'. Die Person sieht den Kommentar in der App.\n"
            ."Vorbereitung auf ein Gespräch: Personen, Dossier, Abschnitt 'Vorbereitung auf das Gespräch' (KI aus Geteiltem, Notizen und Gespräch), 'Neu erstellen'.\n"
            ."Werkzeug anlegen: Werkzeuge, 'Werkzeug anlegen', Felder ausfüllen, 'Sichtbar für die Ausbildung'. Personen bekommen das Kennzeichen 'Coach-Ausbildung' unter Personen, bearbeiten, Zugang.\n"
            ."Themen prüfen: Assistent, Reiter Themen, je Inhalt 'Passt' oder 'Ändern'.\n"
            ."Testbetrieb: Einstellungen, 'Testbetrieb an' und Testadressen. Solange er an ist, gehen Benachrichtigungen nur an diese Adressen.\n";

        return $eigen !== '' ? $t."\nZUSÄTZLICHES WISSEN VON {$coach}\n".$eigen."\n" : $t;
    }
}
