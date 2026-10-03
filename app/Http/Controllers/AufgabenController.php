<?php

namespace App\Http\Controllers;

use App\Coach\Geteilt;
use App\Http\Requests\AufgabeRequest;
use App\Models\Projekt;
use App\Models\Task;
use App\Programs\ProgramAccess;
use App\Programs\Wochenaufgabe;
use App\Support\Anhaenge;
use App\Support\Filter;
use App\Support\Funktionen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AufgabenController extends Controller
{
    public function __construct(protected ProgramAccess $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = Filter::aus($request);
        // Reflexion und Frage der laufenden Kurswoche gehoeren hierher, auch wenn die Woche noch nicht geoeffnet wurde
        app(Wochenaufgabe::class)->sicherstellenAlle($user, $this->access->programsFor($user));

        $tasks = Task::where('user_id', $user->id)->with(['assigner:id,name', 'program:id,title,slug', 'anhaenge.ziel', 'projekt:id,name,farbe,icon', 'comments'])
            ->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')->orderByDesc('is_pinned')->orderBy('due_at')->orderByDesc('created_at')
            ->get()
            ->filter(fn (Task $t) => $filter->passt($t));

        return view('aufgaben.index', [
            'offen' => $tasks->filter(fn (Task $t) => ! $t->isDone())->values(),
            'fertig' => $tasks->filter(fn (Task $t) => $t->isDone())->values(),
            'kurse' => $this->access->auswahlFor($user),
            'projekte' => ! Funktionen::an('projekte') ? collect() : Projekt::where('user_id', $user->id)->orderBy('name')->get(['id', 'name', 'farbe', 'icon']),
            'gemeinschaft' => $this->access->gemeinschaftFor($user)->pluck('title', 'id'),
            'filter' => $filter,
            'bearbeiten' => $request->query('bearbeiten') ? Task::where('user_id', $user->id)->find((int) $request->query('bearbeiten')) : null,
        ]);
    }

    public function store(AufgabeRequest $request): RedirectResponse
    {
        $data = $request->daten();
        $task = Task::create($data + ['user_id' => $request->user()->id, 'source' => $data['unit_id'] ?? null ? 'exercise' : 'manual']);
        app(Anhaenge::class)->speichern($task, $request->input('refs'), $request->user());
        if ($task->visibility !== 'private') {
            app(Geteilt::class)->melden($request->user(), $task);
        }

        // Aus der Wochen- oder Einheitsseite angelegt: dorthin zurueck
        $zurueck = (string) $request->input('zurueck', '');
        if ($zurueck !== '' && str_starts_with($zurueck, url('/').'/')) {
            return redirect()->to($zurueck)->with('meldung', 'Aufgabe angelegt. Du findest sie auch im Journal.');
        }

        return redirect()->route('aufgaben.index')->with('meldung', 'Aufgabe angelegt.')->withFragment('aufgabe-'.$task->id);
    }

    public function update(AufgabeRequest $request, Task $aufgabe): RedirectResponse
    {
        Gate::authorize('update', $aufgabe);
        $vorher = $aufgabe->visibility;
        $aufgabe->update($request->daten());
        app(Anhaenge::class)->speichern($aufgabe, $request->input('refs'), $request->user());
        if ($vorher === 'private' && $aufgabe->visibility !== 'private') {
            app(Geteilt::class)->melden($request->user(), $aufgabe);
        }

        return redirect()->route('aufgaben.index')->with('meldung', 'Gespeichert.');
    }

    public function haken(Request $request, Task $aufgabe): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $aufgabe);
        $an = ! $aufgabe->isDone();
        $aufgabe->forceFill(['done_at' => $an ? now() : null])->save();

        if ($request->expectsJson()) {
            return response()->json(['an' => $an]);
        }

        return back()->with('meldung', $an ? 'Erledigt.' : 'Wieder offen.');
    }

    /** Bewusst auslassen ("diese Woche nicht") oder wieder aufnehmen. Zu, aber nicht erledigt. */
    public function auslassen(Request $request, Task $aufgabe): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $aufgabe);
        $aus = ! $aufgabe->isSkipped();
        $aufgabe->forceFill(['skipped_at' => $aus ? now() : null, 'done_at' => null])->save();

        if ($request->expectsJson()) {
            return response()->json(['aus' => $aus]);
        }

        return back()->with('meldung', $aus ? 'Ausgelassen. Kein Problem, nächste Woche wieder.' : 'Wieder offen.');
    }

    /** Tagesaufgabe: einen Wochentag abhaken. */
    public function tag(Request $request, Task $aufgabe): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $aufgabe);
        $tag = (string) $request->input('tag');
        abort_unless(in_array($tag, ['mo', 'di', 'mi', 'do', 'fr', 'sa', 'so'], true), 422);

        $week = now()->format('o-W');
        $settings = $aufgabe->settings ?? [];
        $days = $settings['days'][$week] ?? [];
        $days = in_array($tag, $days, true) ? array_values(array_diff($days, [$tag])) : array_values(array_unique([...$days, $tag]));
        $settings['days'][$week] = $days;
        $aufgabe->forceFill(['settings' => $settings])->save();

        if ($request->expectsJson()) {
            return response()->json(['tage' => $days]);
        }

        return back();
    }

    public function destroy(Request $request, Task $aufgabe): RedirectResponse
    {
        Gate::authorize('update', $aufgabe);
        $aufgabe->delete();

        return redirect()->route('aufgaben.index')->with('meldung', 'Aufgabe gelöscht.');
    }
}
