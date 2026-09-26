<?php

namespace App\Http\Controllers;

use App\Chat\Chat;
use App\Http\Requests\FrageRequest;
use App\Models\Comment;
use App\Models\Program;
use App\Models\Question;
use App\Models\Reaction;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Fragen an die Coachin im Kursraum (wie lea-kursraum): Frage stellen, beantworten,
 * Status fuer den naechsten Call, Callwunsch. Sichtbar im Kurs oder nur fuer die Coachin.
 */
class FragenController extends Controller
{
    public function __construct(protected Notifier $notifier, protected Chat $chat) {}

    public function index(Request $request, Program $program): View
    {
        Gate::authorize('view', $program);
        $user = $request->user();
        $filter = in_array($request->query('f'), ['offen', 'call', 'erledigt'], true) ? $request->query('f') : '';

        $fragen = Question::where('program_id', $program->id)->sichtbarFuer($user)
            ->when($filter === 'offen', fn ($q) => $q->whereIn('status', ['offen', 'call']))
            ->when($filter === 'call', fn ($q) => $q->where('status', 'call'))
            ->when($filter === 'erledigt', fn ($q) => $q->whereIn('status', ['beantwortet', 'besprochen', 'zu']))
            ->withCount(['answers', 'reactions as call_wuensche' => fn ($r) => $r->where('emoji', Question::CALLWUNSCH)])
            ->with('user:id,name')->latest()->get();

        return view('kurse.fragen', ['program' => $program, 'fragen' => $fragen, 'filter' => $filter, 'coach' => $this->coachName()]);
    }

    public function store(FrageRequest $request, Program $program): RedirectResponse
    {
        Gate::authorize('view', $program);
        $data = $request->validated();
        $frage = Question::create($data + ['program_id' => $program->id, 'user_id' => $request->user()->id, 'visibility' => $data['visibility'] ?? 'program']);

        $this->melden($this->team()->reject(fn ($id) => $id === $request->user()->id), $frage,
            $request->user()->vorname().' hat eine Frage gestellt', $frage->title);

        return redirect()->route('fragen.show', $frage)->with('meldung', 'Deine Frage ist gestellt. Du bekommst Bescheid, wenn jemand antwortet.');
    }

    public function show(Request $request, Question $frage): View
    {
        $this->darf($request->user(), $frage);
        $frage->load(['user:id,name', 'program', 'answers.user:id,name', 'reactions']);

        return view('fragen.show', [
            'frage' => $frage,
            'team' => $this->team(),
            'coach' => $this->coachName(),
            'callWunsch' => $frage->reactions->where('emoji', Question::CALLWUNSCH),
        ]);
    }

    public function antworten(Request $request, Question $frage): RedirectResponse
    {
        $user = $request->user();
        $this->darf($user, $frage);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $antwort = $frage->answers()->create(['user_id' => $user->id, 'body' => trim($data['body'])]);

        $vomTeam = $this->team()->contains($user->id);
        if ($vomTeam && $frage->status === 'offen') {
            $frage->forceFill(['status' => 'beantwortet', 'answered_at' => now()])->save();
        }

        // Fragestellerin, alle, die schon geantwortet haben, und das Team
        $an = collect([$frage->user_id])->merge($frage->answers()->pluck('user_id'))->merge($this->team())
            ->unique()->reject(fn ($id) => $id === $user->id);
        $this->melden($an, $frage, $user->vorname().' hat geantwortet: '.Str::limit($frage->title, 60), Str::limit($antwort->body, 140));

        return redirect()->to(route('fragen.show', $frage).'#antwort-'.$antwort->id);
    }

    /** Status setzen (nur Coachin und Team). */
    public function status(Request $request, Question $frage): RedirectResponse
    {
        Gate::authorize('status', $frage);
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', array_keys(Question::STATUS))]]);
        $frage->forceFill(['status' => $data['status'], 'answered_at' => $frage->answered_at ?? ($data['status'] !== 'offen' ? now() : null)])->save();

        if ($frage->user_id !== $request->user()->id && in_array($data['status'], ['call', 'beantwortet', 'besprochen'], true)) {
            $this->melden(collect([$frage->user_id]), $frage, 'Deine Frage: '.$frage->statusLabel(), $frage->title);
        }

        return back()->with('meldung', 'Status: '.$frage->statusLabel());
    }

    /** Callwunsch an und aus ("Bitte im Call besprechen"). */
    public function call(Request $request, Question $frage): RedirectResponse
    {
        $user = $request->user();
        $this->darf($user, $frage);
        $r = Reaction::where('user_id', $user->id)->where('reactable_type', 'question')->where('reactable_id', $frage->id)->where('emoji', Question::CALLWUNSCH)->first();
        $r ? $r->delete() : Reaction::create(['user_id' => $user->id, 'reactable_type' => 'question', 'reactable_id' => $frage->id, 'emoji' => Question::CALLWUNSCH]);

        return back();
    }

    public function destroy(Request $request, Question $frage): RedirectResponse
    {
        Gate::authorize('delete', $frage);
        $program = $frage->program;
        $frage->answers()->delete();
        $frage->reactions()->delete();
        $frage->delete();

        return $program ? redirect()->route('kurse.fragen', $program)->with('meldung', 'Frage gelöscht.') : redirect()->route('home');
    }

    public function antwortLoeschen(Request $request, Comment $antwort): RedirectResponse
    {
        abort_unless($antwort->commentable_type === 'question', 404);
        Gate::authorize('delete', $antwort);
        $frage = $antwort->commentable;
        $antwort->delete();

        return redirect()->route('fragen.show', $frage);
    }

    protected function darf(User $user, Question $frage): void
    {
        Gate::forUser($user)->authorize('view', $frage);
    }

    protected function team()
    {
        return $this->chat->teamIds();
    }

    protected function coachName(): string
    {
        return app(Branding::class)->coachName();
    }

    protected function melden($userIds, Question $frage, string $titel, string $text): void
    {
        $ids = collect($userIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }
        $this->notifier->send($ids, new Nachricht(
            titel: $titel,
            text: $text,
            url: route('fragen.show', $frage),
            anlass: 'frage',
            tag: 'frage-'.$frage->id,
            knopf: 'Zur Frage',
        ));
    }
}
