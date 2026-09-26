<?php

namespace App\Http\Controllers;

use App\Chat\Chat;
use App\Content\Fundus;
use App\Content\Inhalte;
use App\Models\Membership;
use App\Models\Sammlung;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Nachschlagen: ein Feld fuer alles (Wort sucht, Satz fragt die KI), Themen, Vorschau,
 * Merken und Teilen, Meine Suchen, Mein Archiv, Sammlungen der Coachin.
 */
class NachschlagenController extends Controller
{
    public function __construct(protected Fundus $fundus, protected Inhalte $inhalte, protected Chat $chat) {}

    public function index(Request $request): View|Response
    {
        $user = $request->user();
        $reiter = in_array($request->query('r'), ['verlauf', 'archiv'], true) ? $request->query('r') : 'finden';
        $q = trim((string) $request->query('q', ''));
        $thema = (int) $request->query('thema', 0);
        $ergebnis = null;
        if ($reiter === 'finden' && ($q !== '' || $thema)) {
            $ergebnis = $this->fundus->los($q, $thema ?: null, $user);
        }
        $daten = [
            'reiter' => $reiter,
            'q' => $q,
            'thema' => $thema,
            'ergebnis' => $ergebnis,
            'gemerkt' => $this->inhalte->bookmarkKeys($user),
            'coach' => $user->canManageCurrentTenant(),
            'leute' => $user->canManageCurrentTenant() ? $this->leute() : collect(),
        ];
        if ($request->header('X-Requested-With') === 'fetch' && $reiter === 'finden') {
            return response(view('nachschlagen._ergebnis', $daten)->render());
        }
        if ($reiter === 'verlauf') {
            $daten['verlauf'] = SearchHistory::where('user_id', $user->id)->orderByDesc('id')->get();
            $daten['einzeln'] = null;
            if ($v = (int) $request->query('v')) {
                $h = $daten['verlauf']->firstWhere('id', $v);
                if ($h) {
                    $daten['einzeln'] = $h;
                    $daten['karten'] = $this->fundus->kartenZu((array) $h->items, $user);
                }
            }
        }
        if ($reiter === 'archiv') {
            $models = $this->inhalte->bookmarksFor($user)->map(fn ($z) => $z['model']);
            $daten['karten'] = $this->fundus->karten($models, $user);
        }
        $daten['themen'] = $this->fundus->themenNachGruppen();
        $daten['anzahlGemerkt'] = $daten['gemerkt']->count();

        return view('nachschlagen.index', $daten);
    }

    /** Die Vorschau im Fenster (HTML-Stueck). */
    public function vorschau(Request $request, string $art, int $id): Response
    {
        $m = $this->fundus->finde($art, $id);
        abort_unless($m, 404);
        $user = $request->user();

        return response(view('nachschlagen._vorschau', [
            'k' => $this->fundus->vorschau($m, $user),
            'gemerkt' => $this->inhalte->bookmarkKeys($user),
            'coach' => $user->canManageCurrentTenant(),
            'leute' => $user->canManageCurrentTenant() ? $this->leute() : collect(),
        ])->render());
    }

    /** Die Coachin stellt eine Sammlung zusammen und schickt sie in die 1:1-Gespraeche oder erzeugt einen Link. */
    public function teilen(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canManageCurrentTenant(), 403);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*' => ['string', 'regex:~^[a-z]+-\d+$~'],
            'name' => ['nullable', 'string', 'max:120'],
            'gruss' => ['nullable', 'string', 'max:1000'],
            'an' => ['nullable', 'array', 'max:100'],
            'an.*' => ['integer'],
        ]);
        $items = [];
        foreach ($data['items'] as $key) {
            [$art, $id] = explode('-', $key, 2);
            if ($this->fundus->finde($art, (int) $id)) {
                $items[] = ['art' => $art, 'id' => (int) $id];
            }
        }
        if (! $items) {
            return response()->json(['msg' => 'Nichts ausgewählt.'], 422);
        }
        $an = Membership::where('status', 'active')->whereIn('user_id', array_map('intval', (array) ($data['an'] ?? [])))->pluck('user_id')->all();
        $titel = trim((string) ($data['name'] ?? '')) ?: (count($items) === 1
            ? (string) $this->fundus->finde($items[0]['art'], $items[0]['id'])?->title
            : 'Meine Auswahl vom '.now()->translatedFormat('j. F'));
        $s = Sammlung::create(['user_id' => $user->id, 'title' => $titel, 'greeting' => trim((string) ($data['gruss'] ?? '')) ?: null, 'items' => $items, 'recipients' => $an, 'sent_at' => $an ? now() : null]);

        $geschickt = 0;
        foreach (User::whereIn('id', $an)->get() as $person) {
            $conv = $this->chat->directFor($person);
            $text = ($s->greeting ? $s->greeting."\n\n" : '').(count($items) === 1 ? 'Das hier passt zu dir: ' : 'Ich habe dir etwas zusammengestellt: ').$s->title."\n".$s->url();
            $this->chat->send($conv, $user, ['body' => $text, 'meta' => ['sammlung' => $s->id]]);
            $geschickt++;
        }

        return response()->json(['id' => $s->id, 'link' => $s->url(), 'an' => $geschickt]);
    }

    /** Eine geteilte Sammlung ansehen (nur angemeldet, nur mit passendem Schluessel). */
    public function sammlung(Request $request, Sammlung $sammlung, string $key): View
    {
        abort_unless(hash_equals($sammlung->key, $key), 404);
        $user = $request->user();
        $sammlung->gesehenVon($user);

        return view('nachschlagen.sammlung', [
            'sammlung' => $sammlung,
            'karten' => $this->fundus->kartenZu((array) $sammlung->items, $user),
            'gemerkt' => $this->inhalte->bookmarkKeys($user),
            'coach' => $user->canManageCurrentTenant(),
            'leute' => collect(),
        ]);
    }

    public function verlaufLeeren(Request $request): RedirectResponse
    {
        SearchHistory::where('user_id', $request->user()->id)->delete();

        return redirect()->route('nachschlagen.index', ['r' => 'verlauf'])->with('status', 'Verlauf geleert.');
    }

    /** Personen, an die die Coachin etwas schicken kann. */
    protected function leute()
    {
        return Membership::where('status', 'active')->whereIn('role', ['member', 'client'])->with('user:id,name')->get()
            ->filter->user->map(fn ($m) => ['id' => $m->user_id, 'name' => $m->user->name])->sortBy('name')->values();
    }
}
