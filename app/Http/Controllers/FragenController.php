<?php

namespace App\Http\Controllers;

use App\Chat\Chat;
use App\Coach\Geteilt;
use App\Http\Requests\FrageRequest;
use App\Models\Comment;
use App\Models\Membership;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Question;
use App\Models\QuestionState;
use App\Models\Reaction;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Programs\ProgramAccess;
use App\Programs\Wochenaufgabe;
use App\Support\Anhaenge;
use App\Support\Besuche;
use App\Support\Erwaehnungen;
use App\Tenancy\Branding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Fragen an die Coachin im Kursraum (wie lea-kursraum und lea-kursraum-plus): Frage stellen,
 * beantworten, Antwort auf Antwort, beste Antwort, Herz, Folgen, @-Erwaehnung, Status fuer den
 * naechsten Call, Callwunsch. Sichtbar im Kurs oder nur fuer die Coachin.
 */
class FragenController extends Controller
{
    public const SORT = ['neu' => 'Neueste zuerst', 'alt' => 'Älteste zuerst', 'antworten' => 'Meiste Antworten'];

    public const FILTER = ['' => 'Alle', 'offen' => 'Offen', 'call' => 'Für den Call', 'erledigt' => 'Beantwortet', 'meine' => 'Meine', 'neu' => 'Neue Antworten'];

    public function __construct(protected Notifier $notifier, protected Chat $chat, protected Erwaehnungen $erwaehnungen) {}

    public function index(Request $request, Program $program): View
    {
        Gate::authorize('view', $program);
        $user = $request->user();
        $sicht = $this->sicht($request);
        $fragen = $this->liste($this->fragenQuery([$program->id], $user, $sicht), $user, $sicht)->get();

        return view('kurse.fragen', ['program' => $program, 'fragen' => $fragen, 'coach' => $this->coachName(),
            'aufgabe' => app(Wochenaufgabe::class)->ausAufgabe($request, $user), 'erwaehnbar' => $this->erwaehnungen->personen($program)->values(),
            'vorgabe' => $this->vorgabe($request), 'offen' => $request->filled('titel') || $request->boolean('frage')] + $sicht);
    }

    /** Community (wie im alten Bereich): alle Fragen aus meinen Kursen an einem Ort. */
    public function community(Request $request): View
    {
        app(Besuche::class)->merken($request->user(), 'community');
        $user = $request->user();
        $alle = $this->communityProgramme($user);
        $programme = $alle->filter(fn (Program $p) => $p->gemeinschaft())->values();
        $sicht = $this->sicht($request);
        $kurs = $programme->firstWhere('id', (int) $request->query('k', 0));
        $fragen = $this->liste($this->fragenQuery($kurs ? [$kurs->id] : $alle->pluck('id')->all(), $user, $sicht), $user, $sicht)
            ->with('program:id,title,slug,color')->limit(100)->get();

        return view('community', ['programme' => $programme, 'alle' => $alle, 'kurs' => $kurs, 'fragen' => $fragen, 'coach' => $this->coachName(),
            'geteilt' => $sicht['filter'] === '' && $sicht['q'] === '' ? app(Geteilt::class)->stream($user, $kurs) : collect(),
            'vorgabe' => $this->vorgabe($request), 'offen' => $request->filled('titel') || $request->boolean('frage')] + $sicht);
    }

    /** Frage aus der Community: der Kurs kommt aus dem Formular. */
    public function communityStore(FrageRequest $request): RedirectResponse
    {
        $program = $this->communityProgramme($request->user())->filter(fn (Program $p) => $p->gemeinschaft())->firstWhere('id', (int) $request->input('program_id'));
        abort_unless($program, 422, 'Bitte einen Kurs wählen.');

        return $this->store($request, $program);
    }

    /** Wer ist dabei: das Team und alle aus meinen Gruppenkursen, die sich sichtbar geschaltet haben. */
    public function leute(Request $request): View
    {
        $user = $request->user();
        $programme = $this->communityProgramme($user);
        $kurs = $programme->firstWhere('id', (int) $request->query('k', 0));
        $kursIds = $kurs ? collect([$kurs->id]) : $programme->pluck('id');
        $mitglieder = ProgramMember::whereIn('program_id', $kursIds)->get()->groupBy('user_id');
        $leute = Membership::query()->where('status', 'active')->with('user')->get()
            ->filter(fn ($m) => $m->user && ($m->role->canManage() || ($mitglieder->has($m->user_id) && $m->setting('community_sichtbar'))))
            ->sortBy([fn ($a, $b) => ($b->role->canManage() <=> $a->role->canManage()) ?: strcasecmp($a->user->name, $b->user->name)])->values()
            ->map(fn ($m) => [
                'membership' => $m,
                'user' => $m->user,
                'coach' => $m->role->canManage(),
                'kurse' => $m->role->canManage() ? collect() : $programme->whereIn('id', $mitglieder->get($m->user_id, collect())->pluck('program_id'))->pluck('title'),
                'ueber' => (string) $m->setting('ueber_mich', ''),
                'ich' => $m->user_id === $user->id,
            ]);

        return view('community-leute', ['programme' => $programme, 'kurs' => $kurs, 'leute' => $leute, 'sichtbar' => (bool) $user->membershipIn()?->setting('community_sichtbar'), 'coach' => $this->coachName()]);
    }

    /** Fragen gibt es in Gruppenkursen (Hybrid, Selbstlernkurs, Club), nicht im 1:1 und in Arbeitsbuechern. Eigene Raeume haben nur Programme mit gemeinschaft(). */
    protected function communityProgramme(User $user)
    {
        return app(ProgramAccess::class)->programsFor($user)
            ->filter(fn (Program $p) => ($p->is_published || $user->canManageCurrentTenant()) && ! in_array($p->type, ['one_on_one', 'workbook'], true))->values();
    }

    /** Filter, Sortierung und Suche aus der Adresse. */
    protected function sicht(Request $request): array
    {
        return [
            'filter' => array_key_exists($request->query('f', ''), self::FILTER) ? (string) $request->query('f', '') : '',
            'sort' => array_key_exists($request->query('sort', ''), self::SORT) ? (string) $request->query('sort') : 'neu',
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /** Vorbefuellte Frage, z. B. "Frage dazu" aus einer Uebung, einem Impuls oder einer Folge. */
    protected function vorgabe(Request $request): array
    {
        $refs = array_values(array_filter((array) $request->query('ref', []), fn ($r) => is_string($r) && preg_match('~^[a-z]+:\d+$~', $r)));

        return ['title' => Str::limit(trim((string) $request->query('titel', '')), 200, ''), 'body' => trim((string) $request->query('text', '')), 'refs' => $refs];
    }

    protected function fragenQuery(array $programIds, User $user, array $sicht): Builder
    {
        $q = Question::whereIn('program_id', $programIds)->sichtbarFuer($user);
        $filter = $sicht['filter'];
        $q->when($filter === 'offen', fn ($q) => $q->whereIn('status', ['offen', 'call']))
            ->when($filter === 'call', fn ($q) => $q->where('status', 'call'))
            ->when($filter === 'erledigt', fn ($q) => $q->whereIn('status', ['beantwortet', 'besprochen', 'zu']))
            ->when($filter === 'meine', fn ($q) => $q->where('user_id', $user->id))
            ->when($filter === 'neu', fn ($q) => $q->whereNotNull('last_answer_at')->where(fn ($w) => $w
                ->whereDoesntHave('states', fn ($s) => $s->where('user_id', $user->id)->whereNotNull('seen_at'))
                ->orWhereHas('states', fn ($s) => $s->where('user_id', $user->id)->whereColumn('seen_at', '<', 'questions.last_answer_at'))));
        if ($sicht['q'] !== '') {
            $wort = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $sicht['q']).'%';
            $q->where(fn ($w) => $w->where('title', 'like', $wort)->orWhere('body', 'like', $wort));
        }

        return $q;
    }

    protected function liste(Builder $q, User $user, array $sicht): Builder
    {
        $q->withCount(['answers', 'reactions as call_wuensche' => fn ($r) => $r->where('emoji', Question::CALLWUNSCH)])
            ->with(['user:id,name,avatar_path,updated_at', 'states' => fn ($s) => $s->where('user_id', $user->id)]);

        return match ($sicht['sort']) {
            'alt' => $q->oldest(),
            'antworten' => $q->orderByDesc('answers_count')->latest(),
            default => $q->latest(),
        };
    }

    public function store(FrageRequest $request, Program $program): RedirectResponse
    {
        Gate::authorize('view', $program);
        $user = $request->user();
        $data = $request->validated();
        $refs = $data['refs'] ?? null;
        unset($data['refs'], $data['aufgabe_id'], $data['program_id']);
        $frage = Question::create($data + ['program_id' => $program->id, 'user_id' => $user->id, 'visibility' => $data['visibility'] ?? 'program']);
        $wa = app(Wochenaufgabe::class);
        app(Anhaenge::class)->speichern($frage, $wa->refs($request, $user, $refs), $user);
        $wa->abhaken($request, $user);

        // Das Team bekommt jede Frage, der Kurs die sichtbaren (leise, ohne Mail), Erwaehnte ausdruecklich
        $this->melden($this->team()->reject(fn ($id) => $id === $user->id), $frage, $user->vorname().' hat eine Frage gestellt', $frage->title);
        if ($frage->visibility === 'program') {
            $gruppe = ProgramMember::where('program_id', $program->id)->pluck('user_id')->diff($this->team())->reject(fn ($id) => $id === $user->id);
            $this->melden($gruppe, $frage, 'Neue Frage im Kurs '.$program->title, $user->vorname().': '.$frage->title, 'frage_neu', false);
        }
        $this->erwaehnteMelden($frage, $user, $frage->title."\n".$frage->body, collect([$user->id]));

        return redirect()->route('fragen.show', $frage)->with('meldung', 'Deine Frage ist gestellt. Du bekommst Bescheid, wenn jemand antwortet.');
    }

    public function show(Request $request, Question $frage): View
    {
        $user = $request->user();
        $this->darf($user, $frage);
        $frage->load(['user:id,name,avatar_path,updated_at', 'program', 'answers.user:id,name,avatar_path,updated_at', 'answers.reactions', 'reactions', 'states' => fn ($s) => $s->where('user_id', $user->id)]);
        $sort = array_key_exists($request->query('sort', ''), ['alt' => 1, 'neu' => 1, 'herz' => 1]) ? (string) $request->query('sort') : 'alt';

        // Was seit dem letzten Besuch neu ist, dann den Besuch merken
        $stand = $frage->stateFor($user);
        $seit = $stand?->seen_at;
        QuestionState::updateOrCreate(['question_id' => $frage->id, 'user_id' => $user->id], ['seen_at' => now()]);

        return view('fragen.show', [
            'frage' => $frage,
            'antworten' => $frage->antwortenBaum($sort),
            'sort' => $sort,
            'seit' => $seit,
            'folgen' => $stand?->folgen,
            'team' => $this->team(),
            'coach' => $this->coachName(),
            'callWunsch' => $frage->reactions->where('emoji', Question::CALLWUNSCH),
            'erwaehnbar' => $frage->program ? $this->erwaehnungen->personen($frage->program)->values() : collect(),
        ]);
    }

    /** Neue Antworten seit einer Id, fuers Nachladen ohne Neuladen der Seite. */
    public function neu(Request $request, Question $frage): JsonResponse
    {
        $user = $request->user();
        $this->darf($user, $frage);
        $seit = (int) $request->query('seit', 0);
        $neue = $frage->answers()->where('id', '>', $seit)->where('user_id', '!=', $user->id)->with(['user:id,name,avatar_path,updated_at', 'reactions'])->get();
        $letzte = (int) ($frage->answers()->max('id') ?? $seit);
        if ($neue->isEmpty()) {
            return response()->json(['anzahl' => 0, 'letzte' => $letzte]);
        }
        QuestionState::updateOrCreate(['question_id' => $frage->id, 'user_id' => $user->id], ['seen_at' => now()]);
        $team = $this->team();
        $html = $neue->map(fn (Comment $a) => view('fragen._antwort', ['a' => $a->setRelation('children', collect()), 'frage' => $frage, 'team' => $team, 'seit' => null, 'kind' => (bool) $a->parent_id])->render())->join('');

        return response()->json(['anzahl' => $neue->count(), 'letzte' => $letzte, 'html' => $html]);
    }

    public function antworten(Request $request, Question $frage): RedirectResponse
    {
        $user = $request->user();
        $this->darf($user, $frage);
        if ($frage->istZu()) {
            return back()->with('meldung', 'Diese Frage ist abgeschlossen, hier gibt es keine neuen Antworten mehr.');
        }
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'parent_id' => ['nullable', 'integer']]);
        $eltern = null;
        if (! empty($data['parent_id'])) {
            $eltern = $frage->answers()->whereKey((int) $data['parent_id'])->whereNull('parent_id')->first();
            abort_unless($eltern, 422, 'Diese Antwort gibt es nicht mehr.');
        }
        $antwort = $frage->answers()->create(['user_id' => $user->id, 'body' => trim($data['body']), 'parent_id' => $eltern?->id]);

        $vomTeam = $this->team()->contains($user->id);
        $frage->forceFill(['last_answer_at' => now()] + ($vomTeam && $frage->status === 'offen' ? ['status' => 'beantwortet', 'answered_at' => now()] : []))->save();
        QuestionState::updateOrCreate(['question_id' => $frage->id, 'user_id' => $user->id], ['seen_at' => now()]);

        // Fragestellerin, alle, die schon geantwortet haben, das Team und wer folgt; Stummgeschaltete nicht
        $an = $this->empfaenger($frage)->reject(fn ($id) => $id === $user->id);
        $titel = $eltern && $eltern->user_id !== $user->id && $an->contains($eltern->user_id)
            ? $user->vorname().' hat auf deine Antwort reagiert: '.Str::limit($frage->title, 60)
            : $user->vorname().' hat geantwortet: '.Str::limit($frage->title, 60);
        $this->melden($an, $frage, $titel, Str::limit($antwort->body, 140));
        $this->erwaehnteMelden($frage, $user, $antwort->body, $an->push($user->id));

        return redirect()->to(route('fragen.show', $frage).'#antwort-'.$antwort->id);
    }

    /** Eigene Antwort nachbessern (15 Minuten), das Team jederzeit. */
    public function antwortAendern(Request $request, Comment $antwort): RedirectResponse
    {
        abort_unless($antwort->commentable_type === 'question', 404);
        Gate::authorize('update', $antwort);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $antwort->forceFill(['body' => trim($data['body']), 'edited_at' => now()])->save();

        return redirect()->to(route('fragen.show', $antwort->commentable).'#antwort-'.$antwort->id)->with('meldung', 'Antwort geändert.');
    }

    /** "Das ist die Antwort": die Coachin hebt eine Antwort hervor, die Frage gilt als beantwortet. */
    public function beste(Request $request, Comment $antwort): RedirectResponse
    {
        abort_unless($antwort->commentable_type === 'question' && ! $antwort->parent_id, 404);
        $frage = $antwort->commentable;
        Gate::authorize('status', $frage);
        $an = ! $antwort->is_best;
        $frage->answers()->update(['is_best' => false]);
        if ($an) {
            $antwort->forceFill(['is_best' => true])->save();
            if ($frage->isOffen()) {
                $frage->forceFill(['status' => 'beantwortet', 'answered_at' => $frage->answered_at ?? now()])->save();
            }
            if ($antwort->user_id !== $request->user()->id) {
                $this->melden(collect([$antwort->user_id]), $frage, 'Deine Antwort ist markiert: Das ist die Antwort', Str::limit($frage->title, 100));
            }
        }

        return redirect()->to(route('fragen.show', $frage).'#antwort-'.$antwort->id);
    }

    /** Folgen, stummschalten oder wieder normal. */
    public function folgen(Request $request, Question $frage): RedirectResponse
    {
        $user = $request->user();
        $this->darf($user, $frage);
        $was = $request->validate(['was' => ['required', 'in:folgen,stumm,normal']])['was'];
        QuestionState::updateOrCreate(['question_id' => $frage->id, 'user_id' => $user->id], ['folgen' => match ($was) {
            'folgen' => true, 'stumm' => false, default => null
        }]);

        return back()->with('meldung', match ($was) {
            'folgen' => 'Du bekommst jetzt jede Antwort mit.', 'stumm' => 'Diese Frage ist für dich stumm.', default => 'Wieder wie gewohnt.'
        });
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
        Reaction::where('reactable_type', 'comment')->whereIn('reactable_id', $frage->answers()->pluck('id'))->delete();
        $frage->answers()->delete();
        $frage->reactions()->delete();
        $frage->states()->delete();
        $frage->delete();

        return $program ? redirect()->route('kurse.fragen', $program)->with('meldung', 'Frage gelöscht.') : redirect()->route('home');
    }

    public function antwortLoeschen(Request $request, Comment $antwort): RedirectResponse
    {
        abort_unless($antwort->commentable_type === 'question', 404);
        Gate::authorize('delete', $antwort);
        $frage = $antwort->commentable;
        Reaction::where('reactable_type', 'comment')->where('reactable_id', $antwort->id)->delete();
        $antwort->children()->update(['parent_id' => null]);
        $antwort->delete();

        return redirect()->route('fragen.show', $frage);
    }

    /** Wer eine Antwort mitbekommt: Fragestellerin, Antwortende, Team, Folgende; ohne Stummgeschaltete. */
    protected function empfaenger(Question $frage): Collection
    {
        $stand = QuestionState::where('question_id', $frage->id)->whereNotNull('folgen')->get();

        return collect([$frage->user_id])->merge($frage->answers()->pluck('user_id'))->merge($this->team())
            ->merge($stand->where('folgen', true)->pluck('user_id'))
            ->diff($stand->where('folgen', false)->pluck('user_id'))
            ->unique()->values();
    }

    /** Erwaehnte, die nicht ohnehin schon Bescheid bekommen, erfahren es ausdruecklich. */
    protected function erwaehnteMelden(Question $frage, User $von, ?string $text, Collection $schon): void
    {
        if (! $frage->program) {
            return;
        }
        $ids = $this->erwaehnungen->finden($text, $frage->program)->diff($schon)
            ->filter(fn ($id) => Gate::forUser(User::find($id))->allows('view', $frage));
        $this->melden($ids, $frage, $von->vorname().' hat dich erwähnt: '.Str::limit($frage->title, 60), Str::limit((string) $text, 140));
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

    protected function melden($userIds, Question $frage, string $titel, string $text, string $anlass = 'frage', bool $mail = true): void
    {
        $ids = collect($userIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }
        $this->notifier->send($ids, new Nachricht(
            titel: $titel,
            text: $text,
            url: route('fragen.show', $frage),
            anlass: $anlass,
            tag: 'frage-'.$frage->id,
            mailWennKeinPush: $mail,
            knopf: 'Zur Frage',
        ));
    }
}
