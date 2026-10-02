<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Projekt;
use App\Models\Reflection;
use App\Programs\ProgramAccess;
use App\Programs\Wochenaufgabe;
use App\Support\Anhaenge;
use App\Support\Filter;
use App\Support\Funktionen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Wochenreflexion: drei Fragen, privat bis geteilt. Eine angefangene, noch
 * nicht geteilte Reflexion wird weitergeschrieben statt neu angelegt.
 */
class ReflexionController extends Controller
{
    public const FRAGEN = [
        'went_well' => ['✨', 'Was hat geklappt? Was war cool?', 'Auch die kleinen Dinge zählen.'],
        'challenges' => ['🌱', 'Wo gab es Herausforderungen?', 'Ohne Bewertung, nur benennen.'],
        'focus' => ['🎯', 'Fokus: Wo will ich hin? Meine nächsten Schritte.', 'Ein bis drei konkrete Schritte für die kommende Woche.'],
    ];

    public function __construct(protected ProgramAccess $access, protected Wochenaufgabe $wochenaufgabe) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = Filter::aus($request);
        $entwurf = $request->query('refl')
            ? Reflection::where('user_id', $user->id)->where('visibility', 'private')->find((int) $request->query('refl'))
            : Reflection::where('user_id', $user->id)->where('visibility', 'private')->where('created_at', '>=', now()->subDays(6))->latest()->first();

        return view('reflexion.index', [
            'fragen' => self::FRAGEN,
            'entwurf' => $entwurf,
            'meine' => Reflection::where('user_id', $user->id)->with(['program:id,title', 'anhaenge.ziel', 'projekt:id,name,farbe,icon', 'comments'])->latest()->limit(60)->get()->filter(fn (Reflection $r) => $filter->passt($r))->values(),
            'filter' => $filter,
            'kurse' => $this->access->auswahlFor($user),
            'projekte' => ! Funktionen::an('projekte') ? collect() : Projekt::where('user_id', $user->id)->orderBy('name')->get(['id', 'name', 'farbe', 'icon']),
            'gemeinschaft' => $this->access->gemeinschaftFor($user)->pluck('title', 'id'),
            'woche' => 'Woche '.now()->format('W').' ('.now()->translatedFormat('j. F Y').')',
            'aufgabe' => $this->wochenaufgabe->ausAufgabe($request, $user),
            // Kurswochen zur Auswahl (nur freigeschaltete Wochen getakteter Kurse) und der Rueckblick auf das letzte Vorhaben
            'wochen' => $this->access->programsFor($user)->filter(fn (Program $p) => $p->pacing === 'weekly')
                ->mapWithKeys(fn (Program $p) => [$p->title => $p->steps->filter(fn ($s) => $s->isUnlocked($p))->sortByDesc('position')->values()])->filter(fn ($s) => $s->isNotEmpty()),
            'vorher' => Reflection::where('user_id', $user->id)->when($entwurf, fn ($q) => $q->where('id', '!=', $entwurf->id))->whereNotNull('focus')->where('focus', '!=', '')->latest()->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'refl_id' => ['nullable', 'integer'],
            'went_well' => ['nullable', 'string', 'max:10000'],
            'challenges' => ['nullable', 'string', 'max:10000'],
            'focus' => ['nullable', 'string', 'max:10000'],
            'program_id' => ['nullable', 'integer'],
            'step_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'visibility' => ['nullable', 'in:private,coach,program,all'],
            'aufgabe_id' => ['nullable', 'integer'],
            'refs' => ['nullable', 'array', 'max:12'],
            'refs.*' => ['string', 'max:40'],
        ]);

        $reflection = ! empty($data['refl_id'])
            ? Reflection::where('user_id', $user->id)->where('visibility', 'private')->find($data['refl_id'])
            : null;
        $reflection ??= new Reflection(['user_id' => $user->id, 'week_label' => 'Woche '.now()->format('W').' ('.now()->translatedFormat('j. F Y').')']);

        if (blank($data['went_well'] ?? null) && blank($data['challenges'] ?? null) && blank($data['focus'] ?? null)) {
            return back()->with('fehler', 'Schreib zuerst etwas in eines der drei Felder.');
        }

        $programId = null;
        $stepId = null;
        // Eine Kurswoche bringt ihren Kurs mit
        if (! empty($data['step_id']) && ($s = ProgramStep::with('program')->find((int) $data['step_id'])) && $s->program && $this->access->canView($user, $s->program)) {
            $stepId = $s->id;
            $data['program_id'] = $s->program_id;
        }
        if (! empty($data['program_id']) && ($p = Program::find($data['program_id'])) && $this->access->canView($user, $p)) {
            $programId = $p->id;
        }
        $visibility = $data['visibility'] ?? 'private';
        if ($visibility === 'program' && (! $programId || ! $p->gemeinschaft())) {
            $visibility = 'coach';
        }

        $reflection->fill([
            'went_well' => $data['went_well'] ?? null,
            'challenges' => $data['challenges'] ?? null,
            'focus' => $data['focus'] ?? null,
            'program_id' => $programId,
            'step_id' => $programId ? $stepId : null,
            'project_id' => Projekt::where('user_id', $user->id)->whereKey((int) ($data['project_id'] ?? 0))->value('id'),
            'visibility' => $visibility,
            'shared_at' => $visibility !== 'private' ? ($reflection->shared_at ?? now()) : null,
        ])->save();
        app(Anhaenge::class)->speichern($reflection, $this->wochenaufgabe->refs($request, $user, $data['refs'] ?? null), $user);
        if ($this->wochenaufgabe->abhaken($request, $user)) {
            return redirect()->route('aufgaben.index')->with('meldung', 'Reflexion gespeichert und Aufgabe abgehakt.');
        }
        $this->wochenaufgabe->abhakenArt($user, 'reflexion', $stepId, $programId);

        return redirect()->route('reflexion.index')->with('meldung', $visibility === 'private' ? 'Reflexion gespeichert, nur für dich.' : 'Reflexion gespeichert und geteilt.');
    }

    /** Nachtrag an eine geteilte Reflexion. */
    public function nachtrag(Request $request, Reflection $reflexion): RedirectResponse
    {
        Gate::authorize('update', $reflexion);
        $data = $request->validate(['addendum' => ['required', 'string', 'max:10000']]);
        $reflexion->forceFill(['addendum' => trim(($reflexion->addendum ? $reflexion->addendum."\n\n" : '').$data['addendum'])])->save();

        return back()->with('meldung', 'Nachtrag gespeichert.');
    }

    public function teilen(Request $request, Reflection $reflexion): RedirectResponse
    {
        Gate::authorize('update', $reflexion);
        $visibility = $request->input('visibility') === 'private' ? 'private' : ($reflexion->program_id && $request->input('visibility') === 'program' ? 'program' : 'coach');
        $reflexion->forceFill(['visibility' => $visibility, 'shared_at' => $visibility === 'private' ? null : ($reflexion->shared_at ?? now())])->save();

        return back()->with('meldung', $visibility === 'private' ? 'Nur noch für dich.' : 'Geteilt.');
    }

    public function destroy(Request $request, Reflection $reflexion): RedirectResponse
    {
        Gate::authorize('update', $reflexion);
        $reflexion->delete();

        return redirect()->route('reflexion.index')->with('meldung', 'Reflexion gelöscht.');
    }
}
