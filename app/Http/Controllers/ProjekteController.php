<?php

namespace App\Http\Controllers;

use App\Coach\Geteilt;
use App\Models\Program;
use App\Models\Projekt;
use App\Programs\ProgramAccess;
use App\Support\Funktionen;
use App\Tenancy\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Meine Projekte: anlegen, den Prozessschritt setzen, "Wo ich stehe" als Treppe, teilen. */
class ProjekteController extends Controller
{
    public function __construct(protected ProgramAccess $access) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! Funktionen::an('projekte')) {
            return redirect()->route('home');
        }
        $user = $request->user();
        $projekte = Projekt::where('user_id', $user->id)->with('program:id,title')->withCount(['notes', 'tasks', 'reflections', 'comments'])->orderBy('position')->orderBy('name')->get();
        $bearbeiten = $request->query('bearbeiten') ? $projekte->firstWhere('id', (int) $request->query('bearbeiten')) : null;

        return view('projekte.index', [
            'projekte' => $projekte,
            'ansicht' => $request->query('ansicht') === 'liste' || $projekte->isEmpty() || $bearbeiten ? 'liste' : 'treppe',
            'bearbeiten' => $bearbeiten,
            'gemeinschaft' => $this->access->gemeinschaftFor($user)->pluck('title', 'id'),
            'coach' => app(Branding::class)->coachName(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $p = Projekt::create($this->daten($request) + ['user_id' => $request->user()->id]);
        if ($p->visibility !== 'private') {
            app(Geteilt::class)->melden($request->user(), $p);
        }

        return redirect()->route('projekte.index', ['ansicht' => 'liste'])->with('meldung', 'Projekt angelegt.')->withFragment('projekt-'.$p->id);
    }

    public function update(Request $request, Projekt $projekt): RedirectResponse
    {
        abort_unless($projekt->user_id === $request->user()->id, 403);
        $vorher = $projekt->visibility;
        $projekt->update($this->daten($request));
        if ($vorher === 'private' && $projekt->visibility !== 'private') {
            app(Geteilt::class)->melden($request->user(), $projekt);
        }

        return redirect()->route('projekte.index', ['ansicht' => 'liste'])->with('meldung', 'Gespeichert.')->withFragment('projekt-'.$projekt->id);
    }

    /** Schritt setzen, aus der Treppe oder der Liste. */
    public function schritt(Request $request, Projekt $projekt): JsonResponse|RedirectResponse
    {
        abort_unless($projekt->user_id === $request->user()->id, 403);
        $schritt = (string) $request->input('schritt', '');
        abort_unless($schritt === '' || array_key_exists($schritt, Projekt::SCHRITTE), 422);
        $projekt->forceFill(['schritt' => $schritt ?: null])->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'name' => $projekt->schrittInfo()[0] ?? 'Noch kein Schritt gesetzt']);
        }

        return back()->with('meldung', $schritt ? 'Schritt gesetzt: '.Projekt::SCHRITTE[$schritt][0] : 'Schritt zurückgesetzt.');
    }

    public function destroy(Request $request, Projekt $projekt): RedirectResponse
    {
        abort_unless($projekt->user_id === $request->user()->id, 403);
        $projekt->delete();   // Eintraege bleiben, project_id wird null

        return redirect()->route('projekte.index', ['ansicht' => 'liste'])->with('meldung', 'Projekt entfernt. Die Einträge bleiben erhalten.');
    }

    protected function daten(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'worum' => ['nullable', 'string', 'max:2000'],
            'farbe' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'schritt' => ['nullable', 'in:'.implode(',', array_keys(Projekt::SCHRITTE))],
            'visibility' => ['nullable', 'in:private,coach,program,all'],
            'program_id' => ['nullable', 'integer'],
        ]);
        $data['farbe'] = $data['farbe'] ?? Projekt::FARBEN[0];
        $data['icon'] = $data['icon'] ?? Projekt::ICONS[0];
        $data['schritt'] = $data['schritt'] ?? null;
        $data['visibility'] = $data['visibility'] ?? 'private';
        $data['program_id'] = null;
        if ($data['visibility'] === 'program') {
            $program = Program::find($request->input('program_id'));
            $data['program_id'] = $program && $program->gemeinschaft() && $this->access->canView($request->user(), $program) ? $program->id : null;
            if (! $data['program_id']) {
                $data['visibility'] = 'coach';
            }
        }

        return $data;
    }
}
