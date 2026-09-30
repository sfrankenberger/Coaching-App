<?php

namespace App\Http\Controllers;

use App\Coach\Geteilt;
use App\Models\Note;
use App\Models\Program;
use App\Models\Projekt;
use App\Programs\ProgramAccess;
use App\Programs\Wochenaufgabe;
use App\Support\Anhaenge;
use App\Support\Filter;
use App\Support\Funktionen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotizenController extends Controller
{
    public function __construct(protected ProgramAccess $access, protected Anhaenge $anhaenge, protected Wochenaufgabe $wochenaufgabe) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = Filter::aus($request);

        $notes = Note::where('user_id', $user->id)->with(['program:id,title', 'notable', 'anhaenge.ziel', 'projekt:id,name,farbe,icon', 'comments'])
            ->orderByDesc('is_pinned')->orderByDesc('updated_at')->get()
            ->filter(fn (Note $n) => $filter->passt($n));

        return view('notizen.index', [
            'notes' => $notes->values(),
            'kurse' => $this->access->programsFor($user)->pluck('title', 'id'),
            'projekte' => ! Funktionen::an('projekte') ? collect() : Projekt::where('user_id', $user->id)->orderBy('name')->get(['id', 'name', 'farbe', 'icon']),
            'gemeinschaft' => $this->access->gemeinschaftFor($user)->pluck('title', 'id'),
            'bearbeiten' => $request->query('bearbeiten') ? Note::where('user_id', $user->id)->find((int) $request->query('bearbeiten')) : null,
            'filter' => $filter,
            'aufgabe' => $this->wochenaufgabe->ausAufgabe($request, $user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $note = Note::create($this->validated($request) + ['user_id' => $request->user()->id]);
        $this->bild($request, $note);
        $this->anhaenge->speichern($note, $this->wochenaufgabe->refs($request, $request->user(), $request->input('refs')), $request->user());
        $aufgabe = $this->wochenaufgabe->abhaken($request, $request->user());
        if ($note->visibility !== 'private') {
            app(Geteilt::class)->melden($request->user(), $note);
        }
        if ($aufgabe) {
            return redirect()->route('aufgaben.index')->with('meldung', 'Notiz gespeichert und Aufgabe abgehakt.');
        }

        return redirect()->route('notizen.index')->with('meldung', 'Notiz gespeichert.')->withFragment('notiz-'.$note->id);
    }

    public function update(Request $request, Note $notiz): RedirectResponse
    {
        Gate::authorize('update', $notiz);
        $vorher = $notiz->visibility;
        $notiz->update($this->validated($request));
        $this->bild($request, $notiz);
        $this->anhaenge->speichern($notiz, $request->input('refs'), $request->user());
        if ($vorher === 'private' && $notiz->visibility !== 'private') {
            app(Geteilt::class)->melden($request->user(), $notiz);
        }

        return redirect()->route('notizen.index')->with('meldung', 'Gespeichert.');
    }

    public function destroy(Request $request, Note $notiz): RedirectResponse
    {
        Gate::authorize('update', $notiz);
        $notiz->delete();

        return redirect()->route('notizen.index')->with('meldung', 'Notiz gelöscht.');
    }

    /** Foto zur Notiz: hochladen oder entfernen (liegt unter tenants/{id}/notizen). */
    protected function bild(Request $request, Note $note): void
    {
        if ($request->boolean('bild_weg') && $note->image_path) {
            Storage::delete($note->image_path);
            $note->forceFill(['image_path' => null])->save();
        }
        if ($request->hasFile('bild')) {
            if ($note->image_path) {
                Storage::delete($note->image_path);
            }
            $note->forceFill(['image_path' => $request->file('bild')->store('tenants/'.$note->tenant_id.'/notizen')])->save();
        }
    }

    /** Das Foto ausliefern, nur fuer die Autorin, das Team und wer die Notiz sehen darf. */
    public function foto(Request $request, Note $notiz): StreamedResponse
    {
        $user = $request->user();
        abort_unless($notiz->user_id === $user->id || $user->canManageCurrentTenant() || app(Geteilt::class)->darfSehen($user, $notiz), 403);
        abort_unless($notiz->image_path && Storage::exists($notiz->image_path), 404);

        return Storage::response($notiz->image_path);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:20000'],
            'program_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'visibility' => ['nullable', 'in:private,coach,program,all'],
            'is_pinned' => ['nullable', 'boolean'],
            'refs' => ['nullable', 'array', 'max:12'],
            'refs.*' => ['string', 'max:40'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'bild' => ['nullable', 'image', 'max:8192'],
            'bild_weg' => ['nullable', 'boolean'],
        ]);
        unset($data['refs'], $data['bild'], $data['bild_weg']);
        $data['link_url'] = filled($data['link_url'] ?? null) ? trim($data['link_url']) : null;
        $data['is_pinned'] = (bool) ($data['is_pinned'] ?? false);
        $data['project_id'] = Projekt::where('user_id', $request->user()->id)->whereKey((int) ($data['project_id'] ?? 0))->value('id');
        $data['visibility'] ??= 'private';
        if (! empty($data['program_id'])) {
            $program = Program::find($data['program_id']);
            $data['program_id'] = $program && $this->access->canView($request->user(), $program) ? $program->id : null;
            $gemeinschaft = $program?->gemeinschaft() ?? false;
        }
        if ($data['visibility'] === 'program' && (empty($data['program_id']) || ! $gemeinschaft)) {
            $data['visibility'] = 'coach';
        }

        return $data;
    }
}
