<?php

namespace App\Http\Controllers;

use App\Coach\Lage;
use App\Content\Inhalte;
use App\Models\Answer;
use App\Models\Event;
use App\Models\Exercise;
use App\Models\MediaPosition;
use App\Models\Note;
use App\Models\Offer;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Question;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\Unit;
use App\Programs\Begleitung;
use App\Programs\Wochenaufgabe;
use App\Programs\ProgramAccess;
use App\Programs\ProgressTracker;
use App\Programs\Strecke;
use App\Tenancy\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Kursraum fuer Teilnehmerinnen: Uebersicht, Schritt, Einheit, Uebungen, Fortschritt.
 */
class KursController extends Controller
{
    public function __construct(protected ProgramAccess $access, protected ProgressTracker $progress) {}

    /** Meine Kurse */
    public function index(Request $request): View
    {
        $user = $request->user();
        // 1:1-Begleitungen sind keine Kurse (Sitzungen stehen im Profil und bei den Terminen)
        $begleitung = app(Begleitung::class);
        $programs = $this->access->programsFor($user)->reject(fn (Program $p) => $p->type === 'one_on_one')->map(function (Program $p) use ($user, $begleitung) {
            $p->setAttribute('stand', $this->progress->summary($user, $p));
            $p->setAttribute('call', $p->isGroup() ? $begleitung->eventsQuery($user)->where('program_id', $p->id)->whereNull('user_id')
                ->whereNotIn('type', Event::ALL_DAY_TYPES)->where('starts_at', '>=', now()->subHours(2)->utc())->orderBy('starts_at')->first() : null);

            return $p;
        });
        $meine = $programs->pluck('id');

        // Zugang bis: aus den laufenden Zugaengen der Person (Woo oder Verkauf)
        $bis = collect();
        foreach ($user->entitlements()->current()->whereNotNull('ends_at')->with('offer.programs:id')->get() as $e) {
            foreach ($e->offer?->programs ?? [] as $p) {
                $bis[$p->id] = $bis->has($p->id) ? max($bis[$p->id], $e->ends_at) : $e->ends_at;
            }
        }
        // Schaufenster: sichtbare Angebote, deren Kurse noch fehlen, und angekuendigte Kurse
        $gesperrt = $user->canManageCurrentTenant() ? collect() : Offer::with('programs')->where('is_active', true)->orderBy('title')->get()
            ->filter(fn (Offer $o) => $o->sichtbar() && $o->kaufbar() && $o->programs->isNotEmpty() && $o->programs->pluck('id')->diff($meine)->isNotEmpty());
        $bald = Program::where('is_internal', false)->where('settings->kommt_bald', true)->whereNotIn('id', $meine)->orderBy('position')->get()
            ->reject(fn (Program $p) => $gesperrt->contains(fn (Offer $o) => $o->programs->contains('id', $p->id)));

        return view('kurse.index', ['programs' => $programs, 'bis' => $bis, 'gesperrt' => $gesperrt, 'bald' => $bald, 'coach' => app(Branding::class)->coachName()]);
    }

    /** Uebersicht eines Programms: Schritte, Fortschritt, naechste Einheit */
    public function show(Request $request, Program $program): View
    {
        Gate::authorize('view', $program);
        $user = $request->user();
        $program->load(['steps.units', 'units']);
        $this->touchMember($user, $program);

        $done = $this->progress->completedUnitIds($user, $program);
        $member = ProgramMember::where('program_id', $program->id)->where('user_id', $user->id)->first();

        $begleitung = app(Begleitung::class);

        return view('kurse.show', [
            'naechsterCall' => $begleitung->eventsQuery($user)->where('program_id', $program->id)->whereNull('user_id')
                ->whereNotIn('type', Event::ALL_DAY_TYPES)->upcoming()->limit(5)->get()->first(fn ($e) => ! $e->isPast()),
            'infos' => app(Inhalte::class)->postsQuery($user)->published()->where('visibility', 'program')->where(fn ($k) => Inhalte::fuerProgramme($k, [$program->id]))
                ->latest('published_at')->limit(3)->get(),
            'fragen' => Question::where('program_id', $program->id)->sichtbarFuer($user)->whereIn('status', ['offen', 'call'])->count(),
            'coach' => app(Branding::class)->coachName(),
            'kontingent' => $program->type === 'one_on_one' || (int) ($program->settings['sitzungen_gesamt'] ?? 0) > 0 ? app(Lage::class)->kontingent($user) : null,
            'termine' => $program->units->isEmpty() ? $begleitung->eventsQuery($user)->where('program_id', $program->id)->whereNull('user_id')->upcoming()->orderBy('starts_at')->limit(8)->get() : collect(),
            'program' => $program,
            'stand' => $this->progress->summary($user, $program),
            'done' => $done,
            'aktuellerSchritt' => $this->currentStep($program),
            'member' => $member,
            'pdfMoeglich' => Exercise::whereIn('unit_id', $program->units->pluck('id'))->whereIn('type', Exercise::ANSWERABLE)->exists(),
            'freigabeOffen' => $program->isWorkbook() && $program->teilbar() && ! $user->canManageCurrentTenant() && ($member?->share_mode === null),
        ]);
    }

    /** Freigabe einmal am Anfang (Workbook-Prinzip): alles teilen oder einzeln entscheiden */
    public function freigabe(Request $request, Program $program): RedirectResponse
    {
        Gate::authorize('view', $program);
        abort_unless($program->teilbar(), 403, 'In diesem Kurs wird nichts geteilt.');
        $data = $request->validate(['modus' => ['required', 'in:alles,einzeln']]);
        $member = $this->access->join($request->user(), $program);
        $member->forceFill(['share_mode' => $data['modus']])->save();

        if ($data['modus'] === 'alles') {
            Answer::query()->where('user_id', $request->user()->id)
                ->whereIn('exercise_id', Exercise::whereIn('unit_id', $program->units()->pluck('id'))->pluck('id'))
                ->update(['shared_with_coach' => true, 'shared_at' => now()]);
        }

        return back()->with('meldung', $data['modus'] === 'alles' ? 'Alles klar, deine Antworten sind mit deiner Coachin geteilt.' : 'Alles klar, du entscheidest bei jeder Übung selbst.');
    }

    /** Ein Schritt (Woche oder Modul) mit seinen Einheiten */
    public function schritt(Request $request, Program $program, ProgramStep $schritt): View
    {
        Gate::authorize('view', $program);
        abort_unless($schritt->program_id === $program->id, 404);
        $user = $request->user();
        $program->load(['steps', 'units']);
        abort_unless($schritt->isUnlocked($program) || $user->canManageCurrentTenant(), 403, 'Dieser Schritt ist noch nicht freigeschaltet.');

        $units = $schritt->units()->where('is_published', true)->with('exercises')->get();
        $done = $this->progress->completedUnitIds($user, $program);
        $steps = $program->steps;
        $idx = $steps->search(fn ($s) => $s->id === $schritt->id);

        // Alles, was zu dieser Woche gehoert: Call, Aufgaben, Material, Reflexion
        $begleitung = app(Begleitung::class);
        $termine = $begleitung->eventsQuery($user)->where('step_id', $schritt->id)
            ->with(['attendees' => fn ($a) => $a->where('user_id', $user->id)])->orderBy('starts_at')->get();
        $material = $begleitung->resourcesQuery($user)
            ->whereHas('links', fn ($l) => $l->where(fn ($w) => $w->where('resourceable_type', 'step')->where('resourceable_id', $schritt->id))
                ->orWhere(fn ($w) => $w->where('resourceable_type', 'unit')->whereIn('resourceable_id', $units->pluck('id'))))
            ->orderBy('title')->get();

        // Fenster der Woche: ab Freischaltung bis zur naechsten, sonst die Kalenderwoche des Schritts
        $von = $schritt->unlocks_at?->copy()->startOfDay();
        $bis = $idx < $steps->count() - 1 ? $steps[$idx + 1]->unlocks_at?->copy()->startOfDay() : null;
        $bis ??= $von?->copy()->addDays(7);
        $imFenster = fn ($q) => $von ? $q->where('created_at', '>=', $von->utc())->where('created_at', '<', $bis->utc()) : $q->whereRaw('1 = 0');
        $unitsJeSchritt = $program->units->where('is_published', true)->groupBy('step_id');
        $aktuell = $program->pacing === 'weekly' ? $steps->filter(fn (ProgramStep $s) => $s->isUnlocked($program))->sortByDesc('position')->first() : null;
        // Reflexions- und Fragentag als abhakbare Aufgaben der Woche; was schon geschrieben ist, gilt als erledigt
        $wa = app(Wochenaufgabe::class);
        $wa->sicherstellen($user, $program, $schritt);
        $reflexion = $user->canManageCurrentTenant() ? null : Reflection::where('user_id', $user->id)->where(fn ($q) => $q->where('step_id', $schritt->id)
            ->orWhere(fn ($w) => $w->whereNull('step_id')->where(fn ($x) => $x->where('program_id', $program->id)->orWhereNull('program_id'))->where($imFenster)))->latest()->first();
        $fragen = Question::where('user_id', $user->id)->where('program_id', $program->id)->where($imFenster)->withCount('answers')->latest()->get();
        if (! $user->canManageCurrentTenant()) {
            $reflexion && $wa->abhakenArt($user, 'reflexion', $schritt->id);
            $fragen->isNotEmpty() && $wa->abhakenArt($user, 'frage', $schritt->id);
        }

        return view('kurse.schritt', [
            'band' => $steps->map(fn (ProgramStep $s, $i) => [
                'step' => $s, 'nummer' => $s->week_number ?? $i + 1, 'offen' => $s->isUnlocked($program) || $user->canManageCurrentTenant(),
                'fertig' => ($n = $unitsJeSchritt->get($s->id, collect())->count()) > 0 && $unitsJeSchritt->get($s->id)->pluck('id')->diff($done)->isEmpty(),
                'jetzt' => $aktuell?->id === $s->id, 'hier' => $s->id === $schritt->id,
            ]),
            'aktuell' => $aktuell,
            'reflexion' => $reflexion,
            'fragen' => $fragen,
            'termine' => $termine,
            'aufgaben' => Task::where('user_id', $user->id)->where('step_id', $schritt->id)
                ->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')->orderBy('due_at')->get(),
            // Rueckstand: was aus den letzten vier Wochen noch offen ist (den Fragentag traegt man nicht nach)
            'rueckstand' => Task::where('user_id', $user->id)->whereNull('done_at')->where('kind', '!=', 'frage')
                ->whereIn('step_id', $steps->slice(max(0, $idx - 4), max(0, $idx))->pluck('id'))->with('step:id,title')->orderBy('step_id')->get(),
            'material' => $material,
            'program' => $program,
            'schritt' => $schritt,
            'units' => $units,
            'done' => $done,
            'nummer' => $idx + 1,
            'anzahl' => $steps->count(),
            'vorher' => $idx > 0 ? $steps[$idx - 1] : null,
            'nachher' => $idx < $steps->count() - 1 ? $steps[$idx + 1] : null,
        ]);
    }

    /** Eine Einheit: Video, Text, Uebungsteile, Notiz, erledigt */
    public function einheit(Request $request, Program $program, Unit $einheit): View
    {
        Gate::authorize('view', $program);
        abort_unless($einheit->program_id === $program->id && ($einheit->is_published || $request->user()->canManageCurrentTenant()), 404);
        $user = $request->user();
        $program->load(['steps', 'units']);
        $einheit->load(['exercises', 'step']);
        if ($einheit->step && ! $einheit->step->isUnlocked($program) && ! $user->canManageCurrentTenant()) {
            abort(403, 'Dieser Schritt ist noch nicht freigeschaltet.');
        }
        $this->touchMember($user, $program);

        $ordered = $program->orderedUnits();
        $idx = $ordered->search(fn (Unit $u) => $u->id === $einheit->id);
        $answers = $this->progress->answersFor($user, $einheit);
        $member = ProgramMember::where('program_id', $program->id)->where('user_id', $user->id)->first();
        $material = app(Begleitung::class)->resourcesQuery($user)
            ->whereHas('links', fn ($l) => $l->where('resourceable_type', 'unit')->where('resourceable_id', $einheit->id))->orderBy('title')->get();

        // Spiegel, Aufnahme-Vorlage und Mitnehmen brauchen Antworten aus anderen Einheiten
        $quellIds = $einheit->exercises->map(fn ($e) => $e->options['exercise_id'] ?? null)->filter()->values();
        $quellen = $quellIds->isEmpty() ? collect() : Answer::where('user_id', $user->id)->whereIn('exercise_id', $quellIds)->get()->keyBy('exercise_id');
        $mitnehmen = collect();
        if ($einheit->exercises->contains('type', 'takeaway')) {
            $unitIds = $ordered->pluck('id');
            $mitnehmen = Answer::where('user_id', $user->id)
                ->whereHas('exercise', fn ($q) => $q->whereIn('unit_id', $unitIds)->whereIn('type', ['list', 'pairs', 'letter']))
                ->with('exercise.unit')->get()
                ->sortBy(fn ($a) => sprintf('%05d-%05d', $unitIds->search($a->exercise->unit_id), $a->exercise->position))
                ->map(fn ($a) => ['titel' => $a->exercise->prompt ?: $a->exercise->unit->title, 'text' => Exercise::alsText($a->value['v'] ?? null)])
                ->filter(fn ($z) => $z['text'] !== '')->values();
        }

        $strecke = app(Strecke::class);
        $goldnuggets = Strecke::aktiv($program) && $idx === $ordered->count() - 1 && ! $user->canManageCurrentTenant() ? $strecke->goldnuggets($user, $program) : collect();

        return view('kurse.einheit', [
            'goldnuggets' => $goldnuggets,
            'quellen' => $quellen,
            'mitnehmen' => $mitnehmen,
            'material' => $material,
            'position' => MediaPosition::where('user_id', $user->id)->where('key', 'unit-'.$einheit->id)->value('seconds'),
            'program' => $program,
            'unit' => $einheit,
            'answers' => $answers,
            'uebung' => $this->progress->exerciseSummary($user, $einheit, $answers),
            'erledigt' => $this->progress->completedUnitIds($user, $program)->contains($einheit->id),
            'geteilt' => $answers->isNotEmpty() && $answers->every(fn (Answer $a) => $a->shared_with_coach),
            'shareMode' => $member?->share_mode,
            'notiz' => Note::where('user_id', $user->id)->where('notable_type', 'unit')->where('notable_id', $einheit->id)->first(),
            'nummer' => $idx === false ? 0 : $idx + 1,
            'anzahl' => $ordered->count(),
            'vorher' => $idx > 0 ? $ordered[$idx - 1] : null,
            'nachher' => $idx !== false && $idx < $ordered->count() - 1 ? $ordered[$idx + 1] : null,
            'stand' => $this->progress->summary($user, $program),
        ]);
    }

    /** Welcher Schritt ist gerade dran (Wochentaktung: der letzte freigeschaltete). */
    protected function currentStep(Program $program): ?ProgramStep
    {
        $steps = $program->steps;
        if ($steps->isEmpty()) {
            return null;
        }
        if ($program->pacing !== 'weekly') {
            return $steps->first();
        }

        return $steps->filter(fn (ProgramStep $s) => $s->isUnlocked($program))->last() ?? $steps->first();
    }

    protected function touchMember($user, Program $program): void
    {
        ProgramMember::where('program_id', $program->id)->where('user_id', $user->id)->update(['last_seen_at' => now()]);
    }
}
