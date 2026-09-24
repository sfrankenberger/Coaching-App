<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Task;
use App\Programs\ProgramAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AufgabenController extends Controller
{
    public function __construct(protected ProgramAccess $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $suche = mb_strtolower(trim((string) $request->query('q', '')));

        $tasks = Task::where('user_id', $user->id)->with(['assigner:id,name', 'program:id,title'])
            ->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')->orderByDesc('is_pinned')->orderBy('due_at')->orderByDesc('created_at')
            ->get()
            ->filter(fn (Task $t) => $suche === '' || str_contains(mb_strtolower($t->title.' '.$t->body), $suche));

        return view('aufgaben.index', [
            'offen' => $tasks->filter(fn (Task $t) => ! $t->isDone())->values(),
            'fertig' => $tasks->filter(fn (Task $t) => $t->isDone())->values(),
            'kurse' => $this->access->programsFor($user)->pluck('title', 'id'),
            'bearbeiten' => $request->query('bearbeiten') ? $tasks->firstWhere('id', (int) $request->query('bearbeiten')) : null,
            'suche' => $suche,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $task = Task::create($data + ['user_id' => $request->user()->id, 'source' => 'manual']);

        return redirect()->route('aufgaben.index')->with('meldung', 'Aufgabe angelegt.')->withFragment('aufgabe-'.$task->id);
    }

    public function update(Request $request, Task $aufgabe): RedirectResponse
    {
        abort_unless($aufgabe->user_id === $request->user()->id, 403);
        $aufgabe->update($this->validated($request));

        return redirect()->route('aufgaben.index')->with('meldung', 'Gespeichert.');
    }

    public function haken(Request $request, Task $aufgabe): JsonResponse|RedirectResponse
    {
        abort_unless($aufgabe->user_id === $request->user()->id, 403);
        $an = ! $aufgabe->isDone();
        $aufgabe->forceFill(['done_at' => $an ? now() : null])->save();

        if ($request->expectsJson()) {
            return response()->json(['an' => $an]);
        }

        return back()->with('meldung', $an ? 'Erledigt.' : 'Wieder offen.');
    }

    /** Tagesaufgabe: einen Wochentag abhaken. */
    public function tag(Request $request, Task $aufgabe): JsonResponse|RedirectResponse
    {
        abort_unless($aufgabe->user_id === $request->user()->id, 403);
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
        abort_unless($aufgabe->user_id === $request->user()->id, 403);
        $aufgabe->delete();

        return redirect()->route('aufgaben.index')->with('meldung', 'Aufgabe gelöscht.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'is_daily' => ['nullable', 'boolean'],
            'program_id' => ['nullable', 'integer'],
            'visibility' => ['nullable', 'in:private,coach,program'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);
        $data['is_daily'] = (bool) ($data['is_daily'] ?? false);
        $data['is_pinned'] = (bool) ($data['is_pinned'] ?? false);
        $data['visibility'] ??= 'private';
        if (! empty($data['program_id'])) {
            $program = Program::find($data['program_id']);
            $data['program_id'] = $program && $this->access->canView($request->user(), $program) ? $program->id : null;
        }
        if ($data['visibility'] === 'program' && empty($data['program_id'])) {
            $data['visibility'] = 'coach';
        }

        return $data;
    }
}
