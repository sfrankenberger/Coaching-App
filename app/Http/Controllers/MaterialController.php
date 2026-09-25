<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Program;
use App\Models\Resource;
use App\Programs\Begleitung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends Controller
{
    public function __construct(protected Begleitung $begleitung) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = (string) $request->query('f', '');
        $suche = mb_strtolower(trim((string) $request->query('q', '')));

        $resources = $this->begleitung->resourcesQuery($user)->with('links')->orderByDesc('created_at')->get();
        $recordings = $this->begleitung->recordings($user);
        $gemerkt = Bookmark::where('user_id', $user->id)->get()->map(fn ($b) => $b->bookmarkable_type.'-'.$b->bookmarkable_id)->flip();

        // Programme fuer die Filterpillen (aus den Zuordnungen)
        $programIds = $resources->flatMap(fn (Resource $r) => $r->links->where('resourceable_type', 'program')->pluck('resourceable_id'))
            ->merge($recordings->pluck('program_id'))->filter()->unique();
        $kurse = Program::whereIn('id', $programIds)->orderBy('title')->get(['id', 'title', 'color']);

        $zeilen = collect();
        foreach ($resources as $r) {
            $pid = $r->links->firstWhere('resourceable_type', 'program')?->resourceable_id;
            $zeilen->push(['art' => 'resource', 'id' => $r->id, 'titel' => $r->title, 'text' => $r->description, 'typ' => $r->type, 'url' => $r->hatSeite() ? route('material.show', $r) : $r->target(), 'seite' => $r->hatSeite(), 'kurs' => $pid, 'ts' => $r->created_at, 'geteilt' => $r->links->contains(fn ($l) => $l->resourceable_type === 'user'), 'dauer' => $r->duration, 'model' => $r]);
        }
        foreach ($recordings as $e) {
            $zeilen->push(['art' => 'event', 'id' => $e->id, 'titel' => $e->title, 'text' => $e->recording_duration, 'typ' => 'aufzeichnung', 'url' => route('termine.show', $e), 'kurs' => $e->program_id, 'ts' => $e->starts_at, 'geteilt' => false, 'dauer' => $e->recording_duration, 'model' => $e]);
        }
        $zeilen = $zeilen->sortByDesc('ts')->values()->filter(function ($z) use ($filter, $suche, $gemerkt) {
            if ($filter === 'aufzeichnung' && $z['art'] !== 'event') {
                return false;
            }
            if ($filter === 'gemerkt' && ! $gemerkt->has($z['art'].'-'.$z['id'])) {
                return false;
            }
            if (str_starts_with($filter, 'k') && ctype_digit(substr($filter, 1)) && (int) $z['kurs'] !== (int) substr($filter, 1)) {
                return false;
            }
            if ($suche !== '' && ! str_contains(mb_strtolower($z['titel'].' '.$z['text']), $suche)) {
                return false;
            }

            return true;
        });

        return view('material.index', ['zeilen' => $zeilen, 'filter' => $filter, 'suche' => $suche, 'kurse' => $kurse, 'gemerkt' => $gemerkt]);
    }

    /** Eigene Seite fuer Video und Audio: Player, Zusammenfassung mit Sprungmarken, Abschrift. */
    public function show(Request $request, Resource $material): View|RedirectResponse
    {
        Gate::authorize('view', $material);
        if (! $material->hatSeite()) {
            return redirect()->away($material->target() ?: route('material.index'));
        }
        $kurs = $material->links->firstWhere('resourceable_type', 'program')?->resourceable_id;

        return view('material.show', [
            'r' => $material,
            'kurs' => $kurs ? Program::find($kurs) : null,
            'gemerkt' => Bookmark::where('user_id', $request->user()->id)->where('bookmarkable_type', 'resource')->where('bookmarkable_id', $material->id)->exists(),
        ]);
    }

    /** Datei ausliefern (nur mit Zugang). */
    public function datei(Request $request, Resource $material): StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $material);
        if (! $material->file_path) {
            return redirect()->away($material->url);
        }
        abort_unless(Storage::exists($material->file_path), 404);

        return Storage::response($material->file_path, $material->file_name ?: basename($material->file_path));
    }

    /** Merken oder Vergessen (Material, Termin, Einheit). */
    public function merken(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:resource,event,unit,post,episode,program,step'], 'id' => ['required', 'integer']]);
        $user = $request->user();

        $b = Bookmark::where('user_id', $user->id)->where('bookmarkable_type', $data['type'])->where('bookmarkable_id', $data['id'])->first();
        if ($b) {
            $b->delete();
            $an = false;
        } else {
            Bookmark::create(['user_id' => $user->id, 'bookmarkable_type' => $data['type'], 'bookmarkable_id' => $data['id']]);
            $an = true;
        }
        $anzahl = Bookmark::where('user_id', $user->id)->count();

        if ($request->expectsJson()) {
            return response()->json(['an' => $an, 'anzahl' => $anzahl]);
        }

        return back()->with('meldung', $an ? 'Gemerkt.' : 'Nicht mehr gemerkt.');
    }
}
