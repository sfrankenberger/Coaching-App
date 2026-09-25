<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Exercise;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Unit;
use App\Programs\ProgressTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Was eine Person in einer Einheit tut: erledigt setzen, Uebungsantworten speichern,
 * mit der Coachin teilen, eigene Notiz. Die Seiten selbst zeigt der KursController.
 */
class EinheitController extends Controller
{
    public function __construct(protected ProgressTracker $progress) {}

    public function erledigt(Request $request, Program $program, Unit $einheit): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $program);
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
        Gate::authorize('view', $exercise->unit->program);
        abort_unless($exercise->isAnswerable(), 422);

        $value = $data['value'] ?? null;
        $value = match ($exercise->type) {
            'scale' => filled($value) ? max(1, min(10, (int) $value)) : null,
            'values', 'choice' => array_values(array_filter(array_map('strval', (array) $value))),
            'checkbox' => (bool) $value,
            'list' => collect((array) $value)->map(fn ($z) => mb_substr(trim(strip_tags((string) $z)), 0, 1000))->filter()->take(200)->values()->all(),
            'pairs' => collect((array) $value)->map(fn ($z) => array_map(fn ($x) => mb_substr(trim(strip_tags((string) $x)), 0, 1000), array_slice(array_pad((array) $z, 2, ''), 0, 2)))
                ->filter(fn ($z) => $z[0] !== '' || $z[1] !== '')->take(200)->values()->all(),
            'audio' => abort(422),
            default => mb_substr(strip_tags((string) $value), 0, 50000),
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
        Gate::authorize('view', $program);
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
        Gate::authorize('view', $program);
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
}
