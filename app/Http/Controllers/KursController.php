<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Exercise;
use App\Models\MediaPosition;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Task;
use App\Models\Unit;
use App\Programs\Begleitung;
use App\Programs\ProgramAccess;
use App\Programs\ProgressTracker;
use Illuminate\Http\JsonResponse;
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
        $programs = $this->access->programsFor($user)->map(function (Program $p) use ($user) {
            $p->setAttribute('stand', $this->progress->summary($user, $p));

            return $p;
        });

        return view('kurse.index', ['programs' => $programs]);
    }

    /** Uebersicht eines Programms: Schritte, Fortschritt, naechste Einheit */
    public function show(Request $request, Program $program): View
    {
        Gate::authorize('view-program', $program);
        $user = $request->user();
        $program->load(['steps.units', 'units']);
        $this->touchMember($user, $program);

        $done = $this->progress->completedUnitIds($user, $program);
        $member = ProgramMember::where('program_id', $program->id)->where('user_id', $user->id)->first();

        return view('kurse.show', [
            'program' => $program,
            'stand' => $this->progress->summary($user, $program),
            'done' => $done,
            'aktuellerSchritt' => $this->currentStep($program),
            'member' => $member,
            'freigabeOffen' => $program->isWorkbook() && ! $user->canManageCurrentTenant() && ($member?->share_mode === null),
        ]);
    }

    /** Freigabe einmal am Anfang (Workbook-Prinzip): alles teilen oder einzeln entscheiden */
    public function freigabe(Request $request, Program $program): RedirectResponse
    {
        Gate::authorize('view-program', $program);
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
        Gate::authorize('view-program', $program);
        abort_unless($schritt->program_id === $program->id, 404);
        $user = $request->user();
        $program->load('steps');
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

        return view('kurse.schritt', [
            'termine' => $termine,
            'aufgaben' => Task::where('user_id', $user->id)->where('step_id', $schritt->id)
                ->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')->orderBy('due_at')->get(),
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
        Gate::authorize('view-program', $program);
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

        return view('kurse.einheit', [
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

    public function erledigt(Request $request, Program $program, Unit $einheit): JsonResponse|RedirectResponse
    {
        Gate::authorize('view-program', $program);
        abort_unless($einheit->program_id === $program->id, 404);

        $done = $this->progress->toggle($request->user(), $einheit, $request->has('an') ? $request->boolean('an') : null);
        $stand = $this->progress->summary($request->user(), $program->load('steps', 'units'));

        if ($request->expectsJson()) {
            return response()->json(['erledigt' => $done, 'stand' => ['done' => $stand['done'], 'total' => $stand['total'], 'percent' => $stand['percent']]]);
        }

        return back()->with('meldung', $done ? 'Als erledigt markiert.' : 'Wieder offen.');
    }

    /** Antwort auf einen Uebungsteil speichern (automatisch beim Tippen) */
    public function antwort(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exercise_id' => ['required', 'integer'],
            'value' => ['nullable'],
        ]);

        $exercise = Exercise::with('unit.program')->findOrFail($data['exercise_id']);
        Gate::authorize('view-program', $exercise->unit->program);
        abort_unless($exercise->isAnswerable(), 422);

        $value = $data['value'] ?? null;
        $value = match ($exercise->type) {
            'scale' => filled($value) ? max(1, min(10, (int) $value)) : null,
            'values', 'choice' => array_values(array_filter(array_map('strval', (array) $value))),
            'checkbox' => (bool) $value,
            default => mb_substr(strip_tags((string) $value), 0, 20000),
        };

        $user = $request->user();
        $member = ProgramMember::where('program_id', $exercise->unit->program_id)->where('user_id', $user->id)->first();
        $answer = Answer::firstOrNew(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        if (! $answer->exists && $member?->share_mode === 'alles') {
            $answer->shared_with_coach = true;
            $answer->shared_at = now();
        }
        $answer->value = ['v' => $value];
        $answer->save();

        return response()->json(['ok' => true, 'zeit' => now()->format('H:i')]);
    }

    /** Antworten einer Einheit mit der Coachin teilen oder wieder zuruecknehmen */
    public function teilen(Request $request, Program $program, Unit $einheit): JsonResponse|RedirectResponse
    {
        Gate::authorize('view-program', $program);
        abort_unless($einheit->program_id === $program->id, 404);
        $user = $request->user();

        $answers = $this->progress->answersFor($user, $einheit->load('exercises'));
        $an = $request->has('an') ? $request->boolean('an') : ! ($answers->isNotEmpty() && $answers->every(fn ($a) => $a->shared_with_coach));

        foreach ($einheit->answerableExercises() as $ex) {
            $a = $answers->get($ex->id) ?? new Answer(['user_id' => $user->id, 'exercise_id' => $ex->id, 'value' => ['v' => null]]);
            $a->shared_with_coach = $an;
            $a->shared_at = $an ? now() : null;
            $a->save();
        }

        if ($request->expectsJson()) {
            return response()->json(['geteilt' => $an]);
        }

        return back()->with('meldung', $an ? 'Mit deiner Coachin geteilt.' : 'Nicht mehr geteilt.');
    }

    /** Notiz zu einer Einheit */
    public function notiz(Request $request, Program $program, Unit $einheit): JsonResponse|RedirectResponse
    {
        Gate::authorize('view-program', $program);
        abort_unless($einheit->program_id === $program->id, 404);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:20000']]);
        $user = $request->user();

        $note = Note::firstOrNew(['user_id' => $user->id, 'notable_type' => 'unit', 'notable_id' => $einheit->id]);
        if (blank($data['body'])) {
            if ($note->exists) {
                $note->delete();
            }
        } else {
            $note->fill(['body' => trim($data['body']), 'program_id' => $program->id, 'title' => $einheit->title])->save();
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'zeit' => now()->format('H:i')]);
        }

        return back()->with('meldung', 'Notiz gespeichert.');
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
