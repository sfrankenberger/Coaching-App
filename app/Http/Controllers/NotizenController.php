<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Program;
use App\Programs\ProgramAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NotizenController extends Controller
{
    public function __construct(protected ProgramAccess $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $suche = mb_strtolower(trim((string) $request->query('q', '')));

        $notes = Note::where('user_id', $user->id)->with(['program:id,title', 'notable'])
            ->orderByDesc('is_pinned')->orderByDesc('updated_at')->get()
            ->filter(fn (Note $n) => $suche === '' || str_contains(mb_strtolower($n->title.' '.$n->body), $suche));

        return view('notizen.index', [
            'notes' => $notes->values(),
            'kurse' => $this->access->programsFor($user)->pluck('title', 'id'),
            'bearbeiten' => $request->query('bearbeiten') ? $notes->firstWhere('id', (int) $request->query('bearbeiten')) : null,
            'suche' => $suche,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $note = Note::create($this->validated($request) + ['user_id' => $request->user()->id]);

        return redirect()->route('notizen.index')->with('meldung', 'Notiz gespeichert.')->withFragment('notiz-'.$note->id);
    }

    public function update(Request $request, Note $notiz): RedirectResponse
    {
        Gate::authorize('update', $notiz);
        $notiz->update($this->validated($request));

        return redirect()->route('notizen.index')->with('meldung', 'Gespeichert.');
    }

    public function destroy(Request $request, Note $notiz): RedirectResponse
    {
        Gate::authorize('update', $notiz);
        $notiz->delete();

        return redirect()->route('notizen.index')->with('meldung', 'Notiz gelöscht.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:20000'],
            'program_id' => ['nullable', 'integer'],
            'visibility' => ['nullable', 'in:private,coach,program,all'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);
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
