<?php

namespace App\Content;

use App\Ai\Anthropic;
use App\Models\FinderProfile;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Resource;
use App\Models\SearchHistory;
use App\Models\Taggable;
use App\Models\Tool;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Programs\Begleitung;
use App\Programs\ProgramAccess;
use App\Support\Suche;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Nachschlagen (Fundus): alles, was die Coachin je gemacht hat, an einem Ort. Ein kurzes Wort sucht
 * direkt, ein ganzer Satz geht an die KI, die passende Inhalte mit Begruendung vorschlaegt.
 * Gesperrtes bleibt sichtbar, mit einer Tuer statt eines Links (wie lea-fundus).
 */
class Fundus
{
    public const ARTEN = [
        'post' => ['Impuls', 'lightbulb'],
        'episode' => ['Podcast', 'microphone'],
        'unit' => ['Lektion', 'book-open'],
        'step' => ['Woche', 'layer-group'],
        'program' => ['Kurs', 'graduation-cap'],
        'resource' => ['Material', 'folder-open'],
        'tool' => ['Werkzeug', 'hammer'],
    ];

    public function __construct(
        protected CurrentTenant $current,
        protected Inhalte $inhalte,
        protected ProgramAccess $access,
        protected Begleitung $begleitung,
        protected Anthropic $ai,
        protected Branding $branding,
    ) {}

    /* ---------- Ein Weg fuer alles ---------- */

    /** Thema gewaehlt: Liste. Ein bis zwei Woerter: Suche. Sonst fragt die KI. */
    public function los(string $text, ?int $themaId, User $user): array
    {
        if ($themaId && ($thema = Topic::find($themaId))) {
            $karten = $this->thema($thema, $user);
            SearchHistory::merken($user, $thema->name, 'thema', null, $this->schluessel($karten));

            return ['art' => 'thema', 'was' => $thema->name, 'karten' => $karten];
        }
        $text = trim($text);
        if ($text === '') {
            return ['art' => 'leer', 'fehler' => 'Schreib oder sag ein paar Worte.'];
        }
        $woerter = preg_split('~\s+~u', $text);
        if (count($woerter) <= 2 && ! str_contains($text, '?')) {
            $karten = $this->wort($text, $user);
            if ($karten->isNotEmpty()) {
                SearchHistory::merken($user, $text, 'wort', null, $this->schluessel($karten));

                return ['art' => 'wort', 'was' => $text, 'karten' => $karten];
            }
        }
        $r = $this->frage($text, $user);
        if (! empty($r['fehler'])) {
            return ['art' => 'fehler', 'fehler' => $r['fehler']];
        }
        SearchHistory::merken($user, $text, 'frage', $r['antwort'], $this->schluessel($r['karten']));

        return ['art' => 'frage', 'antwort' => $r['antwort'], 'karten' => $r['karten']];
    }

    /* ---------- Suchen ---------- */

    /** Wortsuche: Titel und Themenfinder-Text (kurz, hilft, Stichworte). */
    public function wort(string $wort, User $user, int $anzahl = 40): Collection
    {
        $wie = '%'.mb_strtolower($wort).'%';
        $treffer = collect();
        foreach ($this->klassen() as $art => $class) {
            $q = $class::query()->whereRaw('LOWER(title) LIKE ?', [$wie]);
            foreach ($this->sichtbar($art, $q)->limit($anzahl)->get() as $m) {
                $treffer->push($m);
            }
        }
        $profile = FinderProfile::query()
            ->where(fn ($q) => $q->whereRaw('LOWER(summary) LIKE ?', [$wie])->orWhereRaw('LOWER(helps) LIKE ?', [$wie])->orWhereRaw('LOWER(keywords) LIKE ?', [$wie]))
            ->whereIn('profilable_type', array_keys($this->klassen()))
            ->limit($anzahl)->with('profilable')->get();
        foreach ($profile as $p) {
            if ($p->profilable && $this->kandidat($p->profilable)) {
                $treffer->push($p->profilable);
            }
        }

        return $this->karten($treffer->unique(fn (Model $m) => $m->getMorphClass().'-'.$m->getKey())->take($anzahl), $user);
    }

    /** Alle Inhalte eines Themas, auch gesperrte (mit Tuer). */
    public function thema(Topic $thema, User $user): Collection
    {
        $models = Taggable::where('topic_id', $thema->id)->with('taggable')->get()
            ->map(fn (Taggable $t) => $t->taggable)->filter(fn ($m) => $m && $this->kandidat($m));

        return $this->karten($models, $user);
    }

    /** Die KI waehlt aus dem Fundus, was zur Frage passt. */
    public function frage(string $frage, User $user): array
    {
        if (! Anthropic::configured($this->current->get())) {
            return ['fehler' => 'Die Suche in ganzen Sätzen ist noch nicht eingerichtet. Probier ein einzelnes Wort.'];
        }
        $zeilen = [];
        $alle = collect();
        foreach ($this->alle() as $m) {
            $alle->put($this->key($m), $m);
            $zeilen[] = $this->zeile($m);
        }
        if (! $zeilen) {
            return ['fehler' => 'Der Fundus ist noch leer.'];
        }
        $coach = $this->branding->coachName();
        $hinweis = trim((string) $this->current->get()?->setting('ai.fundus_hinweis'));
        $system = "Du hilfst Menschen, im Fundus von {$coach} das Passende zu finden. ".($hinweis !== '' ? $hinweis.' ' : '')
            .'Du gibst KEINE eigene Beratung und keine Ratschläge, du wählst aus der Liste aus und sagst, warum es passt. '
            .'Antworte NUR mit JSON: {"antwort":"...","treffer":[{"id":"unit-12","warum":"..."}]} '
            .'antwort: zwei Sätze, warmherzig, ohne Floskeln. Der erste greift das Anliegen auf, der zweite führt zu den Vorschlägen. '
            .'treffer: drei bis fünf Einträge, der wichtigste zuerst, warum jeweils EIN kurzer Satz, höchstens 20 Wörter. '
            .'Bevorzuge Konkretes: Podcastfolgen, Impulse und einzelne Lektionen aus Kursen. '
            .'Bei Lektionen und Wochen sprich in der Begründung vom Kurs, nicht von der Lektion: die Person bekommt Zugang zum ganzen Kurs. '
            .'Einen Kurs nimm nur, wenn das ganze Thema dort behandelt wird und keine einzelne Lektion passt. '
            .'Nie eine Woche und gleichzeitig eine Lektion daraus, nie zwei Einträge, die dasselbe sagen. '
            .'Die Begründung sagt etwas Neues, sie wiederholt nicht die Beschreibung. '
            .'Achte auf den Kurs, aus dem eine Lektion stammt: eine Lektion "Schuld" aus einem Geldkurs handelt von Geld, nicht von Beziehungen. '
            .'Der Titel allein sagt oft zu wenig, lies immer die Beschreibung dazu. '
            .'Nimm nur IDs aus der Liste. Wenn nichts wirklich passt, sag das ehrlich in der Antwort und gib weniger Treffer. '.Anthropic::STIL;
        try {
            $r = $this->ai->json('Anliegen: '.$frage."\n\nFundus (ID | Art | Titel | Worum es geht | Hilft, wenn | Stichworte):\n".implode("\n", $zeilen), $system, 1200);
        } catch (\Throwable $e) {
            report($e);

            return ['fehler' => 'Die Antwort kam nicht an. Probier es gleich noch einmal.'];
        }
        $d = $r['data'];
        $karten = collect();
        foreach ((array) ($d['treffer'] ?? []) as $t) {
            $m = $alle->get((string) ($t['id'] ?? ''));
            if (! $m) {
                continue;
            }
            $k = $this->karte($m, $user, knapp: true);
            $k['warum'] = trim((string) ($t['warum'] ?? '')) ?: null;
            $karten->push($k);
        }

        return ['antwort' => trim((string) ($d['antwort'] ?? '')), 'karten' => $karten];
    }

    /* ---------- Karten ---------- */

    /** @param  iterable<Model>  $models */
    public function karten(iterable $models, User $user): Collection
    {
        $out = collect();
        foreach ($models as $m) {
            $out->push($this->karte($m, $user));
        }

        return $out;
    }

    /** Eine Trefferkarte: Art, Titel, Fundort im Kurs, kurz, hilft, Themen, offen oder Tuer. */
    public function karte(Model $m, User $user, bool $knapp = false): array
    {
        $art = $m->getMorphClass();
        [$label, $icon] = self::ARTEN[$art] ?? [$art, 'file'];
        $f = $m->finder ?? null;
        $kurz = trim((string) ($f?->summary ?: $this->anriss($m, 200)));
        if ($knapp) {
            $kurz = Str::words($kurz, 24, ' ...');
        }
        $offen = $this->darf($m, $user);
        $kurs = $this->kursVon($m);
        $row = $this->inhalte->row($m);
        $tuer = $offen ? null : $this->tuer($m, $kurs);

        return [
            'art' => $art,
            'id' => $m->getKey(),
            'key' => $this->key($m),
            'label' => $label,
            'icon' => $icon,
            'titel' => $kurs && in_array($art, ['unit', 'step'], true) ? $kurs->title : (string) $m->title,
            'eigen' => (string) $m->title,
            'fundort' => $kurs && in_array($art, ['unit', 'step'], true) ? $this->fundort($m) : null,
            'kurz' => mb_substr($kurz, 0, 220),
            'hilft' => $knapp ? null : ($f?->helps ?: null),
            'themen' => $knapp ? [] : $m->topics()->pluck('name')->take(3)->all(),
            'bild' => $row['bild'] ?? null,
            'url' => $offen ? $this->ziel($m, $row) : ($tuer['url'] ?? null),
            'offen' => $offen,
            'tuer' => $tuer,
            'warum' => null,
            'model' => $m,
        ];
    }

    /** Die Vorschau im Fenster: mehr Text, dazu ein Anriss, wenn offen. */
    public function vorschau(Model $m, User $user): array
    {
        $k = $this->karte($m, $user);
        $k['anriss'] = $k['offen'] ? Str::words($this->anriss($m, 900), 90, ' ...') : null;
        $k['themen'] = $m->topics()->pluck('name')->all();
        $k['knopf'] = $k['offen'] ? (in_array($k['art'], ['unit', 'step'], true) ? 'Zur Lektion' : 'Öffnen') : ($k['tuer']['knopf'] ?? 'Mehr dazu');

        return $k;
    }

    /** Modell zu Art und ID, nur aus dem Fundus. */
    public function finde(string $art, int $id): ?Model
    {
        $class = $this->klassen()[$art] ?? null;

        return $class ? $class::find($id) : null;
    }

    /* ---------- Zugang ---------- */

    /** Darf diese Person diesen Inhalt oeffnen? */
    public function darf(Model $m, User $user): bool
    {
        if ($user->canManageCurrentTenant()) {
            return true;
        }

        return match (true) {
            $m instanceof Post => $this->inhalte->canViewPost($user, $m),
            $m instanceof PodcastEpisode => (bool) $m->is_published,
            $m instanceof Tool => Tool::darf($user) && $m->is_published,
            $m instanceof Resource => $this->begleitung->canViewResource($user, $m),
            $m instanceof Program => $this->access->canView($user, $m),
            $m instanceof Unit, $m instanceof ProgramStep => $m->program ? $this->access->canView($user, $m->program) : false,
            default => false,
        };
    }

    /** Was steht zwischen dieser Person und dem Inhalt? */
    public function tuer(Model $m, ?Program $kurs = null): array
    {
        $tenant = $this->current->get();
        $website = (string) ($tenant?->setting('website') ?: '/');
        if ($m instanceof Tool) {
            return ['text' => 'Gehört zur Coach-Ausbildung.', 'knopf' => 'Mehr dazu', 'url' => (string) ($tenant?->setting('ausbildung_url') ?: $website)];
        }
        $kurs ??= $m instanceof Program ? $m : null;
        if (! $kurs) {
            return ['text' => 'Gehört zu einem Angebot von '.$this->branding->coachName().'.', 'knopf' => 'Angebote ansehen', 'url' => (string) ($tenant?->setting('shop.url') ?: $website)];
        }
        $ziel = (string) (($kurs->settings['sales_url'] ?? null) ?: $tenant?->setting('shop.url') ?: $website);

        return ['text' => 'Diesen Kurs hast du noch nicht.', 'knopf' => $kurs->title.' ansehen', 'url' => $ziel];
    }

    /* ---------- Hilfen ---------- */

    /** Alle Inhalte, die im Fundus liegen (veroeffentlicht, nicht intern), fuer die KI-Liste. */
    public function alle(): Collection
    {
        $out = collect();
        foreach ($this->klassen() as $art => $class) {
            foreach ($this->sichtbar($art, $class::query())->with('finder')->orderBy('id')->limit(400)->get() as $m) {
                $out->push($m);
            }
        }

        return $out;
    }

    public function klassen(): array
    {
        return ['post' => Post::class, 'episode' => PodcastEpisode::class, 'unit' => Unit::class, 'step' => ProgramStep::class, 'program' => Program::class, 'resource' => Resource::class, 'tool' => Tool::class];
    }

    /** Nur, was ueberhaupt in den Fundus gehoert: veroeffentlicht, nicht intern, nicht archiviert. */
    protected function sichtbar(string $art, $q)
    {
        $programme = fn ($s) => $s->select('id')->from('programs')->where('is_published', true)->where('is_internal', false);

        return match ($art) {
            'post' => $q->published()->whereIn('visibility', ['members', 'program']),
            'episode' => $q->published(),
            'unit' => $q->where('is_published', true)->whereIn('program_id', $programme),
            'step' => $q->whereIn('program_id', $programme),
            'program' => $q->where('is_published', true)->where('is_internal', false),
            'resource' => $q->where('is_archived', false)->whereHas('links', fn ($l) => $l->whereIn('resourceable_type', ['program', 'step', 'unit'])),
            'tool' => $q->where('is_published', true),
            default => $q,
        };
    }

    protected function kandidat(Model $m): bool
    {
        $art = $m->getMorphClass();
        if (! isset($this->klassen()[$art])) {
            return false;
        }

        return $this->sichtbar($art, $m->newQuery())->whereKey($m->getKey())->exists();
    }

    public function key(Model $m): string
    {
        return $m->getMorphClass().'-'.$m->getKey();
    }

    protected function schluessel(Collection $karten): array
    {
        return $karten->map(fn ($k) => ['art' => $k['art'], 'id' => $k['id']])->values()->all();
    }

    /** Karten zu gespeicherten Schluesseln (Verlauf, Sammlung), in der gespeicherten Reihenfolge. */
    public function kartenZu(array $items, User $user): Collection
    {
        $out = collect();
        foreach ($items as $i) {
            $m = $this->finde((string) ($i['art'] ?? ''), (int) ($i['id'] ?? 0));
            if ($m && $this->kandidat($m)) {
                $out->push($this->karte($m, $user));
            }
        }

        return $out;
    }

    protected function kursVon(Model $m): ?Program
    {
        return match (true) {
            $m instanceof Program => $m,
            $m instanceof Unit, $m instanceof ProgramStep => $m->program,
            default => null,
        };
    }

    /** Wo genau im Kurs steckt es? */
    protected function fundort(Model $m): string
    {
        $eigen = (string) $m->title;
        if ($m instanceof ProgramStep) {
            return 'Dein Thema kommt in der Woche »'.$eigen.'« vor';
        }
        $step = $m instanceof Unit ? $m->step : null;

        return $step ? 'Dein Thema kommt in '.$step->title.', Lektion »'.$eigen.'« vor' : 'Dein Thema kommt in der Lektion »'.$eigen.'« vor';
    }

    protected function ziel(Model $m, array $row): ?string
    {
        if ($m instanceof Tool) {
            return route('werkzeuge.show', $m);
        }
        if ($m instanceof Resource) {
            return $m->hatSeite() ? route('material.show', $m) : ($m->target() ?: route('material.index'));
        }

        return $row['url'] ?? null;
    }

    protected function anriss(Model $m, int $limit): string
    {
        $felder = match (true) {
            $m instanceof Tool => [$m->purpose, $m->fits_when],
            $m instanceof Post => [$m->excerpt, $m->body],
            $m instanceof PodcastEpisode => [$m->summary, $m->excerpt, $m->body],
            $m instanceof Unit => [$m->intro, $m->body],
            $m instanceof ProgramStep => [$m->summary ?? null, $m->intro ?? null],
            $m instanceof Program => [$m->subtitle, $m->description],
            $m instanceof Resource => [$m->summary, $m->description],
            default => [],
        };
        $text = Suche::text(implode(' ', array_filter(array_map(fn ($v) => is_string($v) ? $v : null, $felder))));

        return Str::limit($text, $limit, ' ...');
    }

    /** Eine Zeile fuer die KI-Liste. */
    protected function zeile(Model $m): string
    {
        $art = $m->getMorphClass();
        $f = $m->finder ?? null;
        $wo = '';
        if (in_array($art, ['unit', 'step'], true) && ($kurs = $this->kursVon($m))) {
            $wo = ' aus dem Kurs "'.$kurs->title.'"';
        }
        $stich = is_array($f?->keywords) ? implode(', ', array_slice($f->keywords, 0, 6)) : '';

        return $this->key($m).' | '.(self::ARTEN[$art][0] ?? $art).$wo.' | '.$m->title.' | '
            .mb_substr((string) ($f?->summary ?: $this->anriss($m, 180)), 0, 180).' | '
            .mb_substr((string) ($f?->helps ?? ''), 0, 120).' | '.$stich;
    }

    /** Themen fuer die Auswahlliste, nach Gruppen. */
    public function themenNachGruppen(): Collection
    {
        return Topic::where('is_visible', true)->whereHas('taggables')->withCount('taggables')->orderBy('name')->get()
            ->groupBy(fn (Topic $t) => $t->group ?: 'Weiteres')->sortKeys();
    }

    /** Morph-Klasse zu einer Art (fuer Routen). */
    public static function modelFor(string $art): ?string
    {
        return Relation::getMorphedModel($art);
    }
}
