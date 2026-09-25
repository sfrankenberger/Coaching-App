<?php

namespace App\Ai;

use App\Models\AiSummary;
use App\Models\Event;
use App\Models\FinderProfile;
use App\Models\PodcastEpisode;
use App\Models\ProgramMember;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\Topic;
use App\Models\User;
use App\Tenancy\Branding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * KI-Aufbereitung: Zusammenfassung und Aufgabenvorschlaege zu einer Aufzeichnung,
 * Kapitel, FAQ und Zusammenfassung zu einer Podcastfolge, Themen und
 * Themenfinder-Text zu beliebigen Inhalten.
 */
class Summarizer
{
    public function __construct(protected Anthropic $ai) {}

    /* ---------- Termin (Aufzeichnung) ---------- */

    public function event(Event $event, ?int $requestedBy = null): AiSummary
    {
        $summary = AiSummary::firstOrNew(['summarizable_type' => 'event', 'summarizable_id' => $event->id, 'kind' => 'summary']);
        $summary->fill(['status' => 'pending', 'error' => null, 'requested_by' => $requestedBy ?? $summary->requested_by])->save();

        $stoff = trim((string) $event->transcript);
        if ($stoff === '') {
            $summary->fill(['status' => 'failed', 'error' => 'Keine Abschrift am Termin. Erst die Abschrift eintragen.'])->save();

            return $summary;
        }

        $person = $event->user_id ? $event->user?->vorname() : null;
        $coach = app(Branding::class)->appName();
        $prompt = "Du hilfst der Coachin ({$coach}) beim Aufbereiten einer Aufzeichnung.\n"
            .($person ? "Es ist eine 1:1-Sitzung mit {$person}. Sprich {$person} in der Zusammenfassung direkt an (Du).\n" : "Es ist ein Gruppencall. Sprich die Teilnehmerinnen direkt an (Du).\n")
            ."Titel: {$event->title}\nDatum: ".$event->starts_at->translatedFormat('j. F Y')."\n\nAbschrift:\n".mb_substr($stoff, 0, 120000)."\n\n"
            ."Antworte AUSSCHLIESSLICH mit einem JSON-Objekt, ohne Vorrede, ohne Code-Zaun, mit genau diesen Schlüsseln:\n"
            .'{"zusammenfassung": "Fliesstext mit kurzen Absätzen (Leerzeile dazwischen): worum ging es, die wichtigsten Erkenntnisse, was gesagt wurde, das bleibt. 150 bis 350 Wörter.", '
            .'"kernsaetze": ["2 bis 4 kurze Sätze, die hängen bleiben sollen"], '
            .'"aufgaben": [{"titel": "kurz, als Handlung", "text": "1 bis 2 Sätze, was genau und warum", "fuer": "alle oder der Vorname, falls es an eine bestimmte Person ging"}]}'
            ."\n\nRegeln: 0 bis 6 Aufgaben, nur was wirklich als Aufgabe oder Vorsatz besprochen wurde. Keine Werbesprache. Nichts erfinden.";

        try {
            $r = $this->ai->json($prompt, Anthropic::STIL);
            $d = $r['data'];
            $text = trim((string) ($d['zusammenfassung'] ?? ''));
            if (! empty($d['kernsaetze']) && is_array($d['kernsaetze'])) {
                $text .= "\n\nKernsätze:\n".implode("\n", array_map(fn ($s) => '• '.trim((string) $s), $d['kernsaetze']));
            }
            $tasks = collect($d['aufgaben'] ?? [])->filter(fn ($t) => is_array($t) && filled($t['titel'] ?? null))
                ->map(fn ($t) => ['titel' => Str::limit(trim($t['titel']), 160, ''), 'text' => trim((string) ($t['text'] ?? '')), 'fuer' => trim((string) ($t['fuer'] ?? 'alle')) ?: 'alle'])->values()->all();
            $summary->fill(['status' => 'done', 'body' => $text, 'tasks' => $tasks, 'model' => $r['model'], 'tokens_in' => $r['tokens_in'], 'tokens_out' => $r['tokens_out']])->save();
            if ($text !== '') {
                $event->forceFill(['summary' => $text])->saveQuietly();
            }
        } catch (Throwable $e) {
            $summary->fill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        return $summary;
    }

    /* ---------- Vorbereitung auf ein Gespraech ---------- */

    /**
     * Vorbereitung der Coachin auf das naechste Gespraech mit einer Person (wie lea-coachees):
     * Wo sie steht, was auffaellt, Fragen, woran denken. Nur aus dem, was die Person geteilt hat,
     * dazu die eigenen Notizen der Coachin und das 1:1-Gespraech.
     */
    public function vorbereitung(\App\Models\Membership $m, ?int $requestedBy = null): AiSummary
    {
        $summary = AiSummary::firstOrNew(['summarizable_type' => 'membership', 'summarizable_id' => $m->id, 'kind' => 'vorbereitung']);
        $summary->fill(['status' => 'pending', 'error' => null, 'requested_by' => $requestedBy ?? $summary->requested_by])->save();

        $person = $m->user;
        $stoff = $this->stoffFuerVorbereitung($m);
        $prompt = "Du hilfst einer Coachin, sich auf das nächste Gespräch mit {$person->vorname()} vorzubereiten.\n"
            ."Hier ist alles, was {$person->vorname()} mit ihr geteilt hat, dazu die Notizen der Coachin und die letzten Nachrichten. "
            ."Nichts davon ist für andere bestimmt.\n\n{$stoff}\n\n"
            ."Antworte AUSSCHLIESSLICH mit einem JSON-Objekt, ohne Vorrede, ohne Code-Zaun, mit genau diesen Schlüsseln:\n"
            .'{"wo_sie_steht": "3 bis 5 Sätze", "faellt_auf": ["2 bis 4 Beobachtungen, konkret, mit Bezug"], '
            .'"fragen": ["3 bis 5 offene Fragen für das Gespräch"], "woran_denken": ["0 bis 3 Dinge, die die Coachin nicht vergessen sollte (Zusagen, offene Punkte)"]}'
            ."\n\nRegeln: Schreib an die Coachin (Du). Keine Diagnosen, keine Werbesprache, nichts erfinden. Wenn es wenig Material gibt, sag das ehrlich und halte dich kurz.";

        try {
            $r = $this->ai->json($prompt, Anthropic::STIL);
            $d = $r['data'];
            $liste = fn ($k) => collect($d[$k] ?? [])->filter(fn ($s) => is_string($s) && trim($s) !== '')->map(fn ($s) => '• '.trim($s))->implode("\n");
            $teile = array_filter([
                'Wo sie steht' => trim((string) ($d['wo_sie_steht'] ?? '')),
                'Das fällt auf' => $liste('faellt_auf'),
                'Fragen für das Gespräch' => $liste('fragen'),
                'Woran denken' => $liste('woran_denken'),
            ]);
            $text = collect($teile)->map(fn ($t, $h) => "{$h}\n{$t}")->implode("\n\n");
            $summary->fill(['status' => 'done', 'body' => $text, 'model' => $r['model'], 'tokens_in' => $r['tokens_in'], 'tokens_out' => $r['tokens_out']])->save();
        } catch (Throwable $e) {
            $summary->fill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        return $summary;
    }

    /** Stoff fuer die Vorbereitung: nur Geteiltes, eigene Notizen der Coachin, 1:1-Gespraech. */
    public function stoffFuerVorbereitung(\App\Models\Membership $m): string
    {
        $u = $m->user;
        $teile = [];
        $programme = app(\App\Programs\ProgramAccess::class)->programsFor($u);
        if ($programme->isNotEmpty()) {
            $tracker = app(\App\Programs\ProgressTracker::class);
            $teile[] = "PROGRAMME\n".$programme->map(fn ($p) => '- '.$p->title.': '.$tracker->summary($u, $p)['done'].' von '.$tracker->summary($u, $p)['total'].' erledigt')->implode("\n");
        }
        $lage = app(\App\Coach\Lage::class)->fuer($m);
        $teile[] = "LAGE\n- zuletzt da: ".($m->last_seen_at?->diffForHumans() ?? 'noch nie')
            ."\n- Aufgaben von der Coachin: {$lage['aufgaben'][0]} von {$lage['aufgaben'][1]} erledigt, {$lage['ueberfaellig']} überfällig"
            .($lage['verpasst'] ? "\n- {$lage['verpasst']} Gruppencalls verpasst" : '')
            .($lage['kontingent'] ? "\n- Sitzungen: {$lage['kontingent']['gehabt']} gehabt, {$lage['kontingent']['offen']} offen" : '')
            .($lage['naechster'] ? "\n- nächster Termin: ".$lage['naechster']->title.', '.$lage['naechster']->starts_at->translatedFormat('j. F H:i') : '');

        $refl = Reflection::where('user_id', $u->id)->where('visibility', '!=', 'private')->latest()->limit(5)->get();
        if ($refl->isNotEmpty()) {
            $teile[] = "GETEILTE REFLEXIONEN\n".$refl->map(fn ($r) => '- '.($r->week_label ?: $r->created_at->format('d.m.Y')).': '
                .collect(\App\Http\Controllers\ReflexionController::FRAGEN)->map(fn ($f, $k) => $r->$k ? $f[1].' '.Str::limit($r->$k, 600) : null)->filter()->implode(' | '))->implode("\n");
        }
        $antworten = \App\Models\Answer::where('user_id', $u->id)->where('shared_with_coach', true)->with('exercise')->latest('updated_at')->limit(20)->get()->filter->isFilled();
        if ($antworten->isNotEmpty()) {
            $teile[] = "GETEILTE ÜBUNGSANTWORTEN\n".$antworten->map(fn ($a) => '- '.Str::limit((string) ($a->exercise?->prompt ?: $a->exercise?->title), 120).': '.Str::limit($a->asText(), 500))->implode("\n");
        }
        $notizen = \App\Models\Note::where('user_id', $u->id)->whereIn('visibility', ['coach', 'program', 'all'])->latest()->limit(8)->get();
        if ($notizen->isNotEmpty()) {
            $teile[] = "GETEILTE NOTIZEN\n".$notizen->map(fn ($n) => '- '.Str::limit(trim(($n->title ? $n->title.': ' : '').$n->body), 500))->implode("\n");
        }
        $aufgaben = Task::where('user_id', $u->id)->where(fn ($q) => $q->where('visibility', '!=', 'private')->orWhereNotNull('assigned_by'))->latest()->limit(15)->get();
        if ($aufgaben->isNotEmpty()) {
            $teile[] = "AUFGABEN\n".$aufgaben->map(fn ($t) => '- '.($t->isDone() ? '[erledigt] ' : '[offen] ').$t->title)->implode("\n");
        }
        $eigene = \App\Models\CoachNote::where('user_id', $u->id)->latest()->limit(10)->get();
        if ($eigene->isNotEmpty()) {
            $teile[] = "NOTIZEN DER COACHIN\n".$eigene->map(fn ($n) => '- '.$n->created_at->format('d.m.Y').': '.Str::limit($n->body, 600))->implode("\n");
        }
        if ($conv = app(\App\Chat\Chat::class)->directFor($u, false)) {
            $msgs = \App\Models\Message::where('conversation_id', $conv->id)->latest('id')->limit(30)->get()->reverse();
            if ($msgs->isNotEmpty()) {
                $teile[] = "1:1-GESPRÄCH (neueste zuletzt)\n".$msgs->map(fn ($x) => '- '.$x->created_at->format('d.m. H:i').' '.($x->user_id === $u->id ? $u->vorname() : 'Coachin').': '.Str::limit((string) ($x->body ?: $x->transcript ?: '[Anhang]'), 400))->implode("\n");
            }
        }

        return mb_substr(implode("\n\n", $teile), 0, 60000);
    }

    /** Aus den Vorschlaegen echte Aufgaben machen (Indizes der gewaehlten Vorschlaege). */
    public function createTasks(AiSummary $summary, array $indices, User $by): int
    {
        $event = $summary->summarizable;
        if (! $event instanceof Event) {
            return 0;
        }
        $n = 0;
        foreach ($indices as $i) {
            $t = $summary->tasks[$i] ?? null;
            if (! $t) {
                continue;
            }
            $recipients = $this->recipientsFor($event, $t['fuer'] ?? 'alle');
            foreach ($recipients as $uid) {
                $exists = Task::where('user_id', $uid)->where('title', $t['titel'])->where('source', 'ai_summary')->exists();
                if ($exists) {
                    continue;
                }
                Task::create([
                    'user_id' => $uid,
                    'assigned_by' => $by->id,
                    'program_id' => $event->program_id,
                    'title' => $t['titel'],
                    'body' => $t['text'] ?: null,
                    'source' => 'ai_summary',
                    'visibility' => 'coach',
                    'settings' => ['event_id' => $event->id],
                ]);
                $n++;
            }
        }

        return $n;
    }

    /** Wer eine Aufgabe bekommt: 1:1 die Person, sonst alle im Programm oder die genannte Person. */
    protected function recipientsFor(Event $event, string $fuer): array
    {
        if ($event->user_id) {
            return [$event->user_id];
        }
        if (! $event->program_id) {
            return [];
        }
        $members = ProgramMember::where('program_id', $event->program_id)->with('user:id,name')->get();
        $fuer = mb_strtolower(trim($fuer));
        if ($fuer !== '' && $fuer !== 'alle') {
            $named = $members->filter(fn ($m) => $m->user && mb_strtolower($m->user->vorname()) === $fuer)->pluck('user_id')->all();
            if ($named !== []) {
                return $named;
            }
        }

        return $members->pluck('user_id')->all();
    }

    /* ---------- Podcastfolge ---------- */

    public function episode(PodcastEpisode $e, ?int $requestedBy = null): AiSummary
    {
        $summary = AiSummary::firstOrNew(['summarizable_type' => 'episode', 'summarizable_id' => $e->id, 'kind' => 'summary']);
        $summary->fill(['status' => 'pending', 'error' => null, 'requested_by' => $requestedBy ?? $summary->requested_by])->save();
        $stoff = trim(strip_tags((string) $e->transcript));
        if ($stoff === '') {
            $summary->fill(['status' => 'failed', 'error' => 'Keine Abschrift an der Folge.'])->save();

            return $summary;
        }
        $prompt = "Du hilfst beim Aufbereiten einer Podcastfolge ({$e->show}).\nTitel: {$e->title}\nShownotes: ".mb_substr(strip_tags((string) $e->body), 0, 1500)
            ."\n\nAbschrift (Zeitmarken in Sekunden, falls vorhanden):\n".mb_substr($stoff, 0, 120000)."\n\n"
            ."Antworte AUSSCHLIESSLICH mit einem JSON-Objekt, ohne Vorrede, ohne Code-Zaun, mit genau diesen Schlüsseln:\n"
            .'{"zusammenfassung": "2 bis 3 Sätze, worum es geht, konkret", "kapitel": [{"start": Sekunden als Ganzzahl, "titel": "kurzer Kapiteltitel"}], "faq": [{"frage": "...", "antwort": "2 bis 4 Sätze"}], "schlagworte": ["..."]}'
            ."\n\nRegeln: 5 bis 9 Kapitel, das erste bei 0, Startsekunden passend zur Abschrift. Genau 3 FAQ zu Fragen, die Hörerinnen wirklich haben. 4 bis 8 Schlagworte.";
        try {
            $r = $this->ai->json($prompt, Anthropic::STIL);
            $d = $r['data'];
            $e->forceFill(array_filter([
                'summary' => trim((string) ($d['zusammenfassung'] ?? '')) ?: null,
                'chapters' => is_array($d['kapitel'] ?? null) ? array_values($d['kapitel']) : null,
                'faq' => is_array($d['faq'] ?? null) ? array_values($d['faq']) : null,
                'keywords' => is_array($d['schlagworte'] ?? null) ? array_values($d['schlagworte']) : null,
            ]))->save();
            $summary->fill(['status' => 'done', 'body' => $e->summary, 'model' => $r['model'], 'tokens_in' => $r['tokens_in'], 'tokens_out' => $r['tokens_out']])->save();
        } catch (Throwable $ex) {
            $summary->fill(['status' => 'failed', 'error' => mb_substr($ex->getMessage(), 0, 1000)])->save();
        }

        return $summary;
    }

    /* ---------- Themenfinder ---------- */

    /** Themen zuordnen und den Themenfinder-Text schreiben (Post, Folge, Einheit, Material, Programm, Termin). */
    public function finder(Model $model): ?FinderProfile
    {
        $stoff = $this->stoffFor($model);
        if (trim($stoff) === '') {
            return null;
        }
        $themen = Topic::where('is_visible', true)->orderBy('name')->pluck('name')->all();
        $prompt = "Ordne diesen Inhalt für einen Themenfinder ein.\n\nVorhandene Themen (bevorzugt verwenden, höchstens 3 wählen, nur wenn nötig 1 neues Thema vorschlagen):\n".implode("\n", $themen)
            ."\n\nInhalt:\n".mb_substr($stoff, 0, 40000)."\n\n"
            ."Antworte AUSSCHLIESSLICH mit einem JSON-Objekt, ohne Vorrede, ohne Code-Zaun:\n"
            .'{"themen": ["..."], "kurz": "1 bis 2 Sätze, worum es geht", "hilft": "Ein Satz, der mit \"Hilft, wenn\" beginnt", "stichworte": ["4 bis 8 Stichworte"]}';
        $r = $this->ai->json($prompt, Anthropic::STIL, 1200);
        $d = $r['data'];
        if (is_array($d['themen'] ?? null)) {
            $model->syncTopicsByName(array_slice($d['themen'], 0, 4));
        }

        return FinderProfile::updateOrCreate(
            ['profilable_type' => $model->getMorphClass(), 'profilable_id' => $model->id],
            ['summary' => trim((string) ($d['kurz'] ?? '')) ?: null, 'helps' => trim((string) ($d['hilft'] ?? '')) ?: null, 'keywords' => is_array($d['stichworte'] ?? null) ? array_slice(array_values($d['stichworte']), 0, 8) : null, 'is_checked' => false, 'generated_at' => now()],
        );
    }

    protected function stoffFor(Model $m): string
    {
        $parts = [];
        foreach (['title', 'subtitle', 'excerpt', 'intro', 'description', 'summary', 'body', 'transcript'] as $f) {
            if (isset($m->{$f}) && is_string($m->{$f})) {
                $parts[] = ucfirst($f).': '.trim(strip_tags($m->{$f}));
            }
        }

        return implode("\n\n", $parts);
    }
}
